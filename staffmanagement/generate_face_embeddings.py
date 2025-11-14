"""
Face Embedding Generation Script for Employee Registration

This script processes multiple face photos of an employee and generates
face embeddings for each photo. It supports:
- Processing up to 20 face photos per employee
- Different angles and lighting conditions
- Batch processing from the uploads folder
- Saving embeddings to database

Usage:
    python generate_face_embeddings.py <employee_id> <db_employee_id> <db_host> <db_user> <db_password> <db_name>

Arguments:
    employee_id: The employee's ID (used in filename pattern)
    db_employee_id: The database ID (primary key from employees table)
    db_host: Database host (e.g., localhost)
    db_user: Database username
    db_password: Database password
    db_name: Database name
"""

import sys
import os
import cv2
import numpy as np
import mysql.connector
from insightface.app import FaceAnalysis
from huggingface_hub import snapshot_download
import glob

def initialize_auraface():
    """
    Initialize the AuraFace model for face embedding extraction.
    
    Returns:
        FaceAnalysis: Initialized face analysis object
    """
    print("Initializing AuraFace model...")
    
    # Get the directory where this script is located (now in root)
    script_dir = os.path.dirname(os.path.abspath(__file__))
    # Models are stored in faceid/models/auraface
    auraface_local_dir = os.path.join(script_dir, "faceid", "models", "auraface")
    
    try:
        # Download AuraFace model if not already present
        auraface_model_dir = snapshot_download(
            repo_id="fal/AuraFace-v1",
            local_dir=auraface_local_dir
        )
        
        # Initialize FaceAnalysis
        # Root points to faceid folder where models are stored
        face_app = FaceAnalysis(
            name="auraface",
            providers=['CUDAExecutionProvider', 'CPUExecutionProvider'],
            root=os.path.join(script_dir, "faceid")
        )
        face_app.prepare(ctx_id=0, det_size=(640, 640))
        print("AuraFace model ready (GPU mode).")
        return face_app
        
    except Exception as e:
        print(f"GPU initialization failed: {e}")
        print("Trying CPU mode...")
        try:
            face_app = FaceAnalysis(
                name="auraface",
                providers=['CPUExecutionProvider'],
                root=os.path.join(script_dir, "faceid")
            )
            face_app.prepare(ctx_id=-1, det_size=(640, 640))
            print("AuraFace model ready (CPU mode).")
            return face_app
        except Exception as e2:
            print(f"Error loading AuraFace model: {e2}")
            return None


def extract_embedding_from_image(face_app, image_path):
    """
    Extract face embedding from a single image file.
    
    Args:
        face_app: Initialized FaceAnalysis object
        image_path: Path to the image file
        
    Returns:
        numpy.ndarray: Face embedding (512-dimensional vector) or None if no face detected
    """
    try:
        # Read the image
        img = cv2.imread(image_path)
        if img is None:
            print(f"Error: Could not read image {image_path}")
            return None
        
        # Detect faces and extract embeddings
        faces = face_app.get(img)
        
        if len(faces) == 0:
            print(f"Warning: No face detected in {image_path}")
            return None
        
        if len(faces) > 1:
            print(f"Warning: Multiple faces detected in {image_path}, using the first one")
        
        # Get the normalized embedding from the first face
        embedding = faces[0].normed_embedding
        print(f"Successfully extracted embedding from {os.path.basename(image_path)}")
        
        return embedding
        
    except Exception as e:
        print(f"Error processing {image_path}: {e}")
        return None


def save_embedding_to_database(db_config, employee_id, embedding):
    """
    Save a single face embedding to the database.
    
    Args:
        db_config: Dictionary with database connection parameters
        employee_id: The employee's database ID (foreign key)
        embedding: The face embedding (numpy array)
        
    Returns:
        bool: True if successful, False otherwise
    """
    try:
        # Connect to database
        conn = mysql.connector.connect(
            host=db_config['host'],
            user=db_config['user'],
            password=db_config['password'],
            database=db_config['database']
        )
        cursor = conn.cursor()
        
        # Convert embedding to binary blob
        embedding_blob = embedding.tobytes()
        
        # Insert into face_embeddings table
        query = """
            INSERT INTO face_embeddings (employee_id, embedding_data, created_at)
            VALUES (%s, %s, NOW())
        """
        cursor.execute(query, (employee_id, embedding_blob))
        conn.commit()
        
        embedding_id = cursor.lastrowid
        print(f"Saved embedding to database (ID: {embedding_id})")
        
        cursor.close()
        conn.close()
        
        return True
        
    except Exception as e:
        print(f"Error saving embedding to database: {e}")
        return False


def process_employee_photos(employee_id, db_employee_id, db_config):
    """
    Process all face photos for an employee and generate embeddings.
    
    Args:
        employee_id: The employee's ID (used in filename pattern)
        db_employee_id: The employee's database ID
        db_config: Database configuration dictionary
        
    Returns:
        int: Number of embeddings successfully generated and saved
    """
    # Initialize AuraFace
    face_app = initialize_auraface()
    if face_app is None:
        print("Failed to initialize AuraFace model")
        return 0
    
    # Find all face photos for this employee in the uploads folder
    script_dir = os.path.dirname(os.path.abspath(__file__))
    # Script is now in root, so uploads folder is directly accessible
    uploads_dir = os.path.join(script_dir, "uploads")
    
    # Pattern: employeeID_firstname_lastname_*.jpg or *.png
    # We'll search for any file starting with the employee_id
    pattern = os.path.join(uploads_dir, f"{employee_id}_*.*")
    photo_files = glob.glob(pattern)
    
    if not photo_files:
        print(f"No face photos found for employee {employee_id} in {uploads_dir}")
        return 0
    
    print(f"Found {len(photo_files)} face photo(s) for employee {employee_id}")
    
    success_count = 0
    deleted_count = 0
    
    # Process each photo
    for photo_path in photo_files:
        print(f"\nProcessing: {os.path.basename(photo_path)}")
        
        # Extract embedding from the image
        embedding = extract_embedding_from_image(face_app, photo_path)
        
        if embedding is not None:
            # Save to database
            if save_embedding_to_database(db_config, db_employee_id, embedding):
                success_count += 1
                
                # Delete the image file after successful embedding creation and database save
                try:
                    os.remove(photo_path)
                    print(f"[OK] Deleted image file: {os.path.basename(photo_path)}")
                    deleted_count += 1
                except Exception as e:
                    print(f"[WARNING] Failed to delete image file {os.path.basename(photo_path)}: {e}")
    
    print(f"\n=== Summary ===")
    print(f"Total photos processed: {len(photo_files)}")
    print(f"Embeddings successfully saved: {success_count}")
    print(f"Images deleted: {deleted_count}")
    
    return success_count


def main():
    """
    Main entry point for the script.
    """
    # Check command line arguments
    if len(sys.argv) != 7:
        print("Usage: python generate_face_embeddings.py <employee_id> <db_employee_id> <db_host> <db_user> <db_password> <db_name>")
        sys.exit(1)
    
    # Parse arguments
    employee_id = sys.argv[1]
    db_employee_id = int(sys.argv[2])
    
    db_config = {
        'host': sys.argv[3],
        'user': sys.argv[4],
        'password': sys.argv[5],
        'database': sys.argv[6]
    }
    
    print(f"=== Face Embedding Generation ===")
    print(f"Employee ID: {employee_id}")
    print(f"Database Employee ID: {db_employee_id}")
    print(f"Database: {db_config['database']} @ {db_config['host']}")
    print(f"=" * 40)
    
    # Process the employee's photos
    success_count = process_employee_photos(employee_id, db_employee_id, db_config)
    
    if success_count > 0:
        print(f"\n[SUCCESS] Successfully generated and saved {success_count} face embedding(s)")
        sys.exit(0)
    else:
        print(f"\n[FAILED] Failed to generate face embeddings")
        sys.exit(1)


if __name__ == "__main__":
    main()
