"""
Real-Time Face Verification System using AuraFace

This script implements a face recognition system that:
1. Downloads and initializes the AuraFace model (commercial-friendly, Apache 2.0 license)
2. Allows enrollment of an authorized person by capturing their face embedding
3. Performs real-time verification by comparing new faces against the enrolled embedding
4. Uses cosine similarity to determine if faces match

Key Concepts:
- Face Embedding: A numerical vector (512 dimensions) that uniquely represents a face
- Cosine Similarity: Measures how similar two face embeddings are (0-1 scale)
- YuNet: Fast face detector used to locate faces in frames
- AuraFace: High-quality face recognition model that extracts embeddings
"""

import cv2
import numpy as np
import os
import time
import sys
from insightface.app import FaceAnalysis
from huggingface_hub import snapshot_download
import tkinter as tk
from tkinter import messagebox, font as tkfont

# ============================================================================
# CUSTOM MODERN CONFIRMATION DIALOG
# ============================================================================
class ModernConfirmDialog:
    """
    A modern-styled confirmation dialog with custom styling.
    Designed to match the reference design with warning icon and styled buttons.
    """
    
    def __init__(self, parent, title, message):
        self.result = None
        
        # Create dialog window
        self.dialog = tk.Toplevel(parent)
        self.dialog.title(title)
        self.dialog.configure(bg='#F5F5F5')
        
        # Window configuration
        self.dialog.resizable(False, False)
        self.dialog.attributes('-topmost', True)
        
        # Center the window and set size
        window_width = 500
        window_height = 350
        screen_width = self.dialog.winfo_screenwidth()
        screen_height = self.dialog.winfo_screenheight()
        x = (screen_width - window_width) // 2
        y = (screen_height - window_height) // 2
        self.dialog.geometry(f'{window_width}x{window_height}+{x}+{y}')
        
        # Create main container with padding
        main_frame = tk.Frame(self.dialog, bg='#FFFFFF', padx=40, pady=30)
        main_frame.pack(fill='both', expand=True, padx=1, pady=1)
        
        # Title
        title_label = tk.Label(
            main_frame,
            text=title,
            font=('Segoe UI', 20, 'bold'),
            bg='#FFFFFF',
            fg='#2C3E50'
        )
        title_label.pack(pady=(0, 20))
        
        # Separator line
        separator = tk.Frame(main_frame, height=2, bg='#E0E0E0')
        separator.pack(fill='x', pady=(0, 25))
        
        # Warning icon (using Unicode triangle symbol)
        icon_frame = tk.Frame(main_frame, bg='#FFFFFF')
        icon_frame.pack(pady=(0, 20))
        
        # Create warning triangle
        icon_canvas = tk.Canvas(icon_frame, width=80, height=70, bg='#FFFFFF', highlightthickness=0)
        icon_canvas.pack()
        
        # Draw warning triangle with golden color
        points = [40, 10, 10, 60, 70, 60]  # Triangle points
        icon_canvas.create_polygon(points, fill='#D4A953', outline='#C49843', width=2)
        
        # Draw exclamation mark
        icon_canvas.create_rectangle(36, 22, 44, 42, fill='#FFFFFF', outline='#FFFFFF')  # Line
        icon_canvas.create_oval(36, 46, 44, 54, fill='#FFFFFF', outline='#FFFFFF')  # Dot
        
        # Message
        message_label = tk.Label(
            main_frame,
            text=message,
            font=('Segoe UI', 12),
            bg='#FFFFFF',
            fg='#34495E',
            justify='center',
            wraplength=420
        )
        message_label.pack(pady=(0, 30))
        
        # Button frame
        button_frame = tk.Frame(main_frame, bg='#FFFFFF')
        button_frame.pack(pady=(10, 0))
        
        # No button (white with border)
        no_button = tk.Button(
            button_frame,
            text='No',
            font=('Segoe UI', 11, 'bold'),
            bg='#FFFFFF',
            fg='#2C3E50',
            activebackground='#F0F0F0',
            activeforeground='#2C3E50',
            relief='solid',
            borderwidth=2,
            width=12,
            height=2,
            cursor='hand2',
            command=lambda: self.on_button_click(False)
        )
        no_button.pack(side='left', padx=(0, 15))
        
        # Hover effects for No button
        def on_no_enter(e):
            no_button.config(bg='#F0F0F0')
        
        def on_no_leave(e):
            no_button.config(bg='#FFFFFF')
        
        no_button.bind('<Enter>', on_no_enter)
        no_button.bind('<Leave>', on_no_leave)
        
        # Yes button (golden/yellow styled)
        yes_button = tk.Button(
            button_frame,
            text='Yes',
            font=('Segoe UI', 11, 'bold'),
            bg='#D4A953',
            fg='#FFFFFF',
            activebackground='#C49843',
            activeforeground='#FFFFFF',
            relief='flat',
            borderwidth=0,
            width=12,
            height=2,
            cursor='hand2',
            command=lambda: self.on_button_click(True)
        )
        yes_button.pack(side='left')
        
        # Hover effects for Yes button
        def on_yes_enter(e):
            yes_button.config(bg='#C49843')
        
        def on_yes_leave(e):
            yes_button.config(bg='#D4A953')
        
        yes_button.bind('<Enter>', on_yes_enter)
        yes_button.bind('<Leave>', on_yes_leave)
        
        # Bind escape key to cancel
        self.dialog.bind('<Escape>', lambda e: self.on_button_click(False))
        
        # Make dialog modal
        self.dialog.transient(parent)
        self.dialog.grab_set()
        
    def on_button_click(self, value):
        """Handle button click"""
        self.result = value
        self.dialog.grab_release()  # Release the grab before destroying
        self.dialog.destroy()
    
    def show(self):
        """Show the dialog and wait for result"""
        self.dialog.update_idletasks()  # Ensure dialog is fully drawn
        self.dialog.deiconify()  # Make sure dialog is visible
        self.dialog.wait_window()
        return self.result

def show_modern_confirmation(title, message):
    """
    Show a modern styled confirmation dialog.
    Uses a simplified approach to prevent freezing.
    
    Args:
        title (str): Dialog title
        message (str): Dialog message
    
    Returns:
        bool: True if Yes clicked, False if No clicked
    """
    # Create root window
    root = tk.Tk()
    root.withdraw()
    
    # Create and show dialog
    dialog = ModernConfirmDialog(root, title, message)
    
    # Process events to show dialog
    root.update()
    
    # Wait for dialog result
    result = dialog.show()
    
    # Clean up
    try:
        root.quit()
    except:
        pass
    
    try:
        root.destroy()
    except:
        pass
    
    return result if result is not None else False

# ============================================================================
# CONFIGURATION SECTION - Attendance Cooldown Settings
# ============================================================================
# Configure attendance restrictions and cooldown behavior

# Enable/Disable 1-hour cooldown after login (prevents accidental logout)
ENABLE_LOGIN_COOLDOWN = False  # Set to False to disable cooldown feature

# Cooldown duration in minutes (only applies if ENABLE_LOGIN_COOLDOWN is True)
LOGIN_COOLDOWN_MINUTES = 60  # Default: 60 minutes (1 hour)

# Enable/Disable restriction for re-login after logout
ENABLE_LOGOUT_RESTRICTION = True  # Set to False to allow re-login after logout

# ============================================================================

# Import attendance logger for SQLite database logging
try:
    from attendance_logger import get_logger
    attendance_logger = get_logger()
    ATTENDANCE_LOGGING_ENABLED = True
    print("✓ Attendance logging enabled")
except ImportError as e:
    print(f"⚠️  Warning: Attendance logging disabled - {e}")
    attendance_logger = None
    ATTENDANCE_LOGGING_ENABLED = False

# --- 1. SETUP AND INITIALIZATION ---

# ============================================================================
# STEP 1: Download and Initialize AuraFace Model
# ============================================================================
# AuraFace is a deep learning model trained to extract unique "face embeddings"
# A face embedding is like a fingerprint - a unique numerical representation of a face
# This model is Apache 2.0 licensed, making it free for commercial use

print("Initializing AuraFace model...")

# Get the directory where this script is located
script_dir = os.path.dirname(os.path.abspath(__file__))
auraface_local_dir = os.path.join(script_dir, "models", "auraface")

try:
    # Download the entire AuraFace model directory from Hugging Face
    # This only downloads once - subsequent runs use the cached version
    auraface_model_dir = snapshot_download(
        repo_id="fal/AuraFace-v1",  # Repository containing the model
        local_dir=auraface_local_dir  # Where to save the model locally
    )
    print(f"AuraFace model downloaded to: {auraface_model_dir}")
    
    # Initialize FaceAnalysis with AuraFace
    # This object will handle both face detection and embedding extraction
    from insightface.app import FaceAnalysis
    face_app = FaceAnalysis(
        name="auraface",  # Use the auraface model we just downloaded
        providers=['CPUExecutionProvider'], 
        root=script_dir  # Root directory where models are stored
    )
    # Prepare the model for inference
    # ctx_id=0 means use GPU 0, det_size is the input size for detection
    face_app.prepare(ctx_id=0, det_size=(640, 640))
    print("AuraFace model ready.")
except Exception as e:
    print(f"Error loading AuraFace model: {e}")
    # Fallback to CPU if CUDA/GPU fails
    try:
        from insightface.app import FaceAnalysis
        face_app = FaceAnalysis(
            name="auraface",
            providers=['CPUExecutionProvider'],  # CPU only
            root=script_dir
        )
        # ctx_id=-1 means use CPU
        face_app.prepare(ctx_id=-1, det_size=(640, 640))
        print("AuraFace model ready (CPU mode).")
    except Exception as e2:
        print(f"Error loading model: {e2}")
        print("Please ensure you have an internet connection and the required dependencies installed.")
        exit()


# ============================================================================
# STEP 2: Download and Initialize YuNet Face Detector
# ============================================================================
# YuNet is a lightweight, fast face detector from OpenCV
# It's used to quickly find faces in video frames before we extract embeddings
# This two-stage approach (detect then recognize) is more efficient

print("Initializing YuNet face detector...")
try:
    import urllib.request
    
    # Create models directory if it doesn't exist
    yunet_dir = os.path.join(script_dir, "models", "openCV_YuNet")
    os.makedirs(yunet_dir, exist_ok=True)
    
    yunet_model_path = os.path.join(yunet_dir, "face_detection_yunet_2023mar.onnx")
    
    # Download YuNet model from OpenCV's GitHub if not already present
    if not os.path.exists(yunet_model_path):
        print("Downloading YuNet model...")
        yunet_url = "https://github.com/opencv/opencv_zoo/raw/main/models/face_detection_yunet/face_detection_yunet_2023mar.onnx"
        urllib.request.urlretrieve(yunet_url, yunet_model_path)
        print("YuNet model downloaded successfully.")
    
    # Create the face detector object
    # This will be used to find face locations and landmarks in each frame
    face_detector = cv2.FaceDetectorYN_create(yunet_model_path, "", (0, 0))
    print("YuNet model ready.")
except Exception as e:
    print(f"Error initializing YuNet: {e}")
    print("Please ensure you have an internet connection to download the model.")
    exit()

# --- 2. ENROLLMENT: CAPTURE AND SAVE THE AUTHORIZED PERSON'S FACE ---

# Path where we'll load the authorized embeddings from database
# This is a .npy (NumPy) file containing all registered employees' embeddings
# Stored in the database folder
AUTHORIZED_EMBEDDINGS_PATH = os.path.join(script_dir, "database", "authorized_embeddings.npy")
# Legacy single-person enrollment file (for manual enrollment)
AUTHORIZED_EMBEDDING_PATH = os.path.join(script_dir, "authorized_embedding.npy")

def is_frontal_face(landmarks, frame_width, frame_height):
    """
    Check if a face is frontal based on landmark positions.
    
    A frontal face means the person is looking directly at the camera.
    This is important for accurate face recognition.
    
    How it works:
    1. Checks if the nose is centered between the eyes (not turned left/right)
    2. Checks if both eyes are level (not tilted)
    
    Args:
        landmarks: Array of facial landmarks (eye positions, nose, mouth corners)
        frame_width: Width of the video frame
        frame_height: Height of the video frame
    
    Returns:
        True if face is frontal, False otherwise
    """
    if landmarks is None or len(landmarks[0]) < 5:
        return False
    
    # Get key landmarks (facial feature points detected by YuNet)
    # Index 0 = right eye, 1 = left eye, 2 = nose tip
    right_eye = landmarks[0][0]
    left_eye = landmarks[0][1]
    nose = landmarks[0][2]
    
    # Check 1: Nose should be horizontally between the eyes
    # If nose is too far to one side, the person is looking away
    eye_center_x = (right_eye[0] + left_eye[0]) / 2
    nose_dist_from_center = abs(nose[0] - eye_center_x)
    eye_distance = np.linalg.norm(right_eye - left_eye)
    
    # Tolerance: nose can be off-center by a small fraction of the eye distance
    # 0.15 = 15% tolerance for slight head rotation (stricter than before)
    if nose_dist_from_center > eye_distance * 0.15:
        return False
        
    # Check 2: Eyes should be roughly level vertically
    # If one eye is much higher than the other, head is tilted
    eye_level_diff = abs(right_eye[1] - left_eye[1])
    if eye_level_diff > eye_distance * 0.12: # Allow for minimal head tilt (12% tolerance - stricter)
        return False
        
    return True

def is_face_close_enough(box, frame_width, frame_height):
    """
    Check if face is close enough to the camera based on face size.
    
    For accurate face recognition, the face should be:
    - Not too far (face too small = less detail)
    - Not too close (face too large = may be cut off)
    
    We calculate what percentage of the frame the face occupies.
    
    Args:
        box: Bounding box of the face [x, y, width, height]
        frame_width: Width of the video frame
        frame_height: Height of the video frame
    
    Returns:
        Tuple: (is_good_distance: bool, status: str)
               status can be "good", "too_far", or "too_close"
    """
    x, y, w, h = box
    
    # Calculate face area relative to frame
    face_area = w * h
    frame_area = frame_width * frame_height
    face_ratio = face_area / frame_area
    
    # Face should occupy at least 8% of the frame (close enough)
    # and at most 50% (not too close)
    MIN_FACE_RATIO = 0.08  # 8% of frame - minimum acceptable size
    MAX_FACE_RATIO = 0.50  # 50% of frame - maximum acceptable size
    
    if face_ratio < MIN_FACE_RATIO:
        return False, "too_far"
    elif face_ratio > MAX_FACE_RATIO:
        return False, "too_close"
    else:
        return True, "good"

def enroll_person():
    """
    Enroll a new person by capturing their frontal face.
    
    This function:
    1. Opens the webcam
    2. Detects faces in real-time
    3. Checks if the face is frontal
    4. When user presses 'c', captures the face embedding
    5. Saves the embedding to a file for future comparisons
    
    The saved embedding becomes the "authorized person" that
    the system will recognize in the verification stage.
    
    Returns:
        bool: True if enrollment succeeded, False if cancelled
    """
    print("\n--- Enrollment Process ---")
    print("Please look directly at the camera. Press 'c' to capture.")
    print("Press 'f' to toggle fullscreen mode.")
    
    # Open webcam (0 = default camera)
    cap = cv2.VideoCapture(0)
    authorized_embedding = None
    
    # Create fullscreen window for enrollment
    enrollment_window = "Enrollment"
    cv2.namedWindow(enrollment_window, cv2.WINDOW_NORMAL)
    cv2.setWindowProperty(enrollment_window, cv2.WND_PROP_FULLSCREEN, cv2.WINDOW_FULLSCREEN)
    is_fullscreen = True
    
    while True:
        # Read a frame from the webcam
        ret, frame = cap.read()
        if not ret:
            break
            
        display_frame = frame.copy()
        
        # Set input size for YuNet detector
        height, width, _ = frame.shape
        face_detector.setInputSize((width, height))
        
        # Detect faces in the current frame
        # Returns: faces array with [x, y, w, h, landmarks, confidence]
        _, faces = face_detector.detect(frame)
        
        # Default status message
        status_text = "Look Forward. Press 'c' to capture."
        box_color = (0, 255, 255) # Yellow
        is_ready_to_capture = False

        # Check if exactly one face is detected
        if faces is not None and len(faces) == 1:
            face_data = faces[0]
            # Extract bounding box coordinates
            box = face_data[0:4].astype(int)
            # Extract facial landmarks (5 points: 2 eyes, nose, 2 mouth corners)
            landmarks = face_data[4:14].reshape((5, 2)).astype(int)

            # Check if the detected face is frontal
            if is_frontal_face(np.array([landmarks]), width, height):
                status_text = "Frontal face detected. Ready! Press 'c'."
                box_color = (0, 255, 0) # Green - ready to capture
                is_ready_to_capture = True
            else:
                status_text = "Not Frontal. Please look forward."
                box_color = (0, 0, 255) # Red - not ready

            # Draw bounding box around the face for visual feedback
            cv2.rectangle(display_frame, (box[0], box[1]), (box[0] + box[2], box[1] + box[3]), box_color, 2)
        
        # Display status message on screen
        cv2.putText(display_frame, status_text, (20, 40), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 0, 0), 2)
        
        # Resize frame for fullscreen if enabled
        if is_fullscreen:
            # Get screen resolution
            screen_width = int(cv2.getWindowImageRect(enrollment_window)[2]) or 1920
            screen_height = int(cv2.getWindowImageRect(enrollment_window)[3]) or 1080
            
            # Calculate aspect ratios
            frame_h, frame_w = display_frame.shape[:2]
            screen_aspect = screen_width / screen_height
            frame_aspect = frame_w / frame_h
            
            # Resize to fill screen while maintaining aspect ratio
            if frame_aspect > screen_aspect:
                new_width = screen_width
                new_height = int(screen_width / frame_aspect)
            else:
                new_height = screen_height
                new_width = int(screen_height * frame_aspect)
            
            # Resize frame
            resized_frame = cv2.resize(display_frame, (new_width, new_height))
            
            # Create black canvas of screen size
            canvas = np.zeros((screen_height, screen_width, 3), dtype=np.uint8)
            
            # Center the frame on canvas
            y_offset = (screen_height - new_height) // 2
            x_offset = (screen_width - new_width) // 2
            canvas[y_offset:y_offset+new_height, x_offset:x_offset+new_width] = resized_frame
            
            cv2.imshow(enrollment_window, canvas)
        else:
            cv2.imshow(enrollment_window, display_frame)
        
        # Check for key presses
        key = cv2.waitKey(1) & 0xFF
        if key == ord('c') and is_ready_to_capture:
            # User pressed 'c' and face is ready - capture the embedding!
            
            # Use AuraFace to extract the face embedding
            # This converts the face image into a 512-dimensional vector
            faces_detected = face_app.get(frame)
            
            if len(faces_detected) > 0:
                # Get the normalized embedding (vector representation of the face)
                authorized_embedding = faces_detected[0].normed_embedding
                # Save it to disk for future verification
                np.save(AUTHORIZED_EMBEDDING_PATH, authorized_embedding)
                print(f"Enrollment successful! Embedding saved to {AUTHORIZED_EMBEDDING_PATH}")
                break
        elif key == ord('f'):
            # Toggle fullscreen mode
            is_fullscreen = not is_fullscreen
            if is_fullscreen:
                cv2.setWindowProperty(enrollment_window, cv2.WND_PROP_FULLSCREEN, cv2.WINDOW_FULLSCREEN)
                print("Fullscreen mode enabled")
            else:
                cv2.setWindowProperty(enrollment_window, cv2.WND_PROP_FULLSCREEN, cv2.WINDOW_NORMAL)
                print("Fullscreen mode disabled")
        elif key == ord('q'):
            # User pressed 'q' to quit enrollment
            break
            
    # Clean up
    cap.release()
    cv2.destroyAllWindows()
    return authorized_embedding is not None

def check_employee_schedule(employee_db_id):
    """
    Check if an employee has a schedule for today.
    
    Args:
        employee_db_id (int): The database ID of the employee
    
    Returns:
        bool: True if employee has schedule for today, False otherwise
    """
    try:
        from datetime import datetime
        from database.init_local_db import get_db_connection
        
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Get current day of week (0=Monday, 6=Sunday)
        day_of_week = datetime.now().weekday()
        today = datetime.now().strftime('%Y-%m-%d')
        
        # Check if employee has an active schedule for today
        cursor.execute("""
            SELECT sp.id, sp.start_time, sp.end_time, sp.period_name
            FROM employee_schedules es
            JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
            WHERE es.employee_id = ?
              AND es.is_active = 1
              AND sp.day_of_week = ?
              AND sp.is_active = 1
              AND (es.end_date IS NULL OR es.end_date >= ?)
            ORDER BY es.effective_date DESC
            LIMIT 1
        """, (employee_db_id, day_of_week, today))
        
        schedule = cursor.fetchone()
        conn.close()
        
        # Return True if schedule exists, False otherwise
        return schedule is not None
        
    except Exception as e:
        print(f"⚠️  Error checking employee schedule: {e}")
        # On error, allow attendance (fail-safe behavior)
        return True

def check_undertime_and_confirm(employee_db_id):
    """
    Check if the user is trying to logout before scheduled end time (undertime).
    If yes, show a confirmation dialog.
    
    Args:
        employee_db_id (int): The database ID of the employee
    
    Returns:
        bool: True if user confirms logout (or not undertime), False if user cancels
    """
    try:
        from datetime import datetime
        from database.init_local_db import get_db_connection
        
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Get current time and day of week
        now = datetime.now()
        current_time = now.time()
        day_of_week = now.weekday()
        today = now.strftime('%Y-%m-%d')
        
        # Get employee's schedule end time for today
        cursor.execute("""
            SELECT sp.end_time, sp.period_name
            FROM employee_schedules es
            JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
            WHERE es.employee_id = ?
              AND es.is_active = 1
              AND sp.day_of_week = ?
              AND sp.is_active = 1
              AND (es.end_date IS NULL OR es.end_date >= ?)
            ORDER BY es.effective_date DESC
            LIMIT 1
        """, (employee_db_id, day_of_week, today))
        
        schedule = cursor.fetchone()
        conn.close()
        
        # If no schedule, allow logout without confirmation
        if schedule is None:
            return True
        
        end_time_str, period_name = schedule
        
        # Parse scheduled end time (format: HH:MM:SS)
        try:
            end_hour, end_minute, end_second = map(int, end_time_str.split(':'))
            scheduled_end = datetime.now().replace(
                hour=end_hour,
                minute=end_minute,
                second=end_second,
                microsecond=0
            )
            
            # Check if current time is before scheduled end time (undertime)
            if now < scheduled_end:
                print(f"⚠️  User attempting early logout (before scheduled end time)")
                
                # Create a simple hidden root window for messagebox
                root = tk.Tk()
                root.withdraw()
                root.attributes('-topmost', True)
                root.update()
                
                # Show simple confirmation dialog
                result = messagebox.askyesno(
                    "Early Logout - Undertime",
                    "You are logging out before your scheduled time.\n\n"
                    "You will be marked as UNDERTIME.\n\n"
                    "Are you sure you want to logout now?",
                    icon='warning'
                )
                
                # Destroy the root window
                root.destroy()
                
                if result:
                    print(f"✓ User confirmed early logout (undertime)")
                    return True
                else:
                    print(f"✗ User cancelled early logout")
                    return False
            else:
                # Not undertime, allow logout
                return True
                
        except (ValueError, AttributeError) as e:
            print(f"⚠️  Error parsing schedule time '{end_time_str}': {e}")
            # On error, allow logout without confirmation
            return True
            
    except Exception as e:
        print(f"⚠️  Error checking undertime: {e}")
        # On error, allow logout without confirmation (fail-safe)
        return True

def load_profile_pictures(employee_info, user_profile_dir):
    """
    Load all employee profile pictures into memory to avoid disk I/O in the loop.
    Supports both exact filename matches and filename patterns with timestamps.
    """
    profile_pics = {}
    default_pic = None
    
    # Try to load a default picture
    for ext in ['png', 'jpg', 'jpeg']:
        default_path = os.path.join(user_profile_dir, f"user.{ext}")
        if os.path.exists(default_path):
            default_pic = cv2.imread(default_path)
            if default_pic is not None:
                print(f"✓ Default profile picture loaded from {default_path}")
                break

    for info in employee_info:
        emp_code = info.get('employee_code')
        if not emp_code:
            continue
        
        loaded_pic = None
        
        # Try exact match first (e.g., MA22013613.jpg)
        for ext in ['jpg', 'png', 'jpeg']:
            path = os.path.join(user_profile_dir, f"{emp_code}.{ext}")
            if os.path.exists(path):
                pic = cv2.imread(path)
                if pic is not None:
                    loaded_pic = pic
                    print(f"✓ Loaded profile picture for {emp_code}: {path}")
                    break
        
        # If exact match not found, try pattern match (e.g., MA22013613_*.jpg)
        if loaded_pic is None:
            try:
                # Get all files in the directory
                all_files = os.listdir(user_profile_dir)
                
                # Look for files that start with employee code
                for filename in all_files:
                    # Check if filename starts with employee code
                    if filename.startswith(emp_code):
                        # Check if it has a valid image extension
                        if filename.lower().endswith(('.jpg', '.jpeg', '.png')):
                            path = os.path.join(user_profile_dir, filename)
                            pic = cv2.imread(path)
                            if pic is not None:
                                loaded_pic = pic
                                print(f"✓ Loaded profile picture for {emp_code}: {path}")
                                break
            except Exception as e:
                print(f"⚠️  Error searching for profile picture for {emp_code}: {e}")
        
        if loaded_pic is not None:
            profile_pics[emp_code] = loaded_pic
        elif default_pic is not None:
            profile_pics[emp_code] = default_pic
            print(f"⚠️  Using default picture for {emp_code}")
        else:
            print(f"⚠️  No profile picture found for {emp_code}")
            
    print(f"✓ Loaded {len(profile_pics)} profile pictures into memory.")
    return profile_pics

# --- 3. MAIN VERIFICATION LOOP ---

def run_verification():
    """
    Run the real-time face verification system.
    
    This is the main function that:
    1. Loads the enrolled person's face embedding
    2. Continuously captures video from webcam
    3. Detects faces in each frame
    4. When a frontal face is stable for 1.5 seconds, performs verification
    5. Compares the detected face with the enrolled face using cosine similarity
    6. Displays verification result (VERIFIED or UNAUTHORIZED)
    
    How Face Verification Works:
    - Extract embedding from detected face (512 numbers)
    - Compare with enrolled embedding using dot product (cosine similarity)
    - If similarity > 0.6 (60%), faces match = VERIFIED
    - If similarity < 0.6, faces don't match = UNAUTHORIZED
    
    The higher the similarity score, the more confident the match.
    """
    # Check if we have authorized embeddings from database
    if os.path.exists(AUTHORIZED_EMBEDDINGS_PATH):
        print("Loading authorized embeddings from database...")
        try:
            # Load multi-person embeddings from database sync
            data = np.load(AUTHORIZED_EMBEDDINGS_PATH, allow_pickle=True).item()
            all_embeddings = data['embeddings']  # Shape: (N, 512)
            employee_ids = data['employee_ids']
            employee_info = data['employee_info']
            
            print(f"✓ Loaded {data['total_embeddings']} embeddings for {data['unique_employees']} employee(s)")
            print(f"  Last updated: {data['last_update']}")
            
            # Show registered employees
            unique_employees = {}
            for info in employee_info:
                emp_id = info['db_id']
                if emp_id not in unique_employees:
                    unique_employees[emp_id] = info
            
            print("\nRegistered employees:")
            for emp_id, info in unique_employees.items():
                print(f"  - {info['name']} ({info['employee_code']})")
            
            use_multi_person = True
            
        except Exception as e:
            print(f"Error loading database embeddings: {e}")
            print("Falling back to single-person mode...")
            use_multi_person = False
    else:
        use_multi_person = False
    
    # Fallback to single-person enrollment if database embeddings not available
    if not use_multi_person:
        if not os.path.exists(AUTHORIZED_EMBEDDING_PATH):
            print("No authorized person enrolled.")
            if not enroll_person():
                print("Enrollment failed or was cancelled. Exiting.")
                return
        
        # Load the enrolled person's face embedding from disk
        # This is the "reference" we'll compare all detected faces against
        authorized_embedding = np.load(AUTHORIZED_EMBEDDING_PATH)
        all_embeddings = authorized_embedding.reshape(1, -1)  # Convert to 2D array
        employee_ids = [0]  # Dummy ID for single person
        employee_info = [{'db_id': 0, 'employee_code': 'ENROLLED', 'name': 'Authorized Person'}]
        print("Using single-person enrollment mode")

    # Pre-load all profile pictures to avoid disk I/O in the loop
    user_profile_dir = os.path.join(script_dir, "database", "user_profile")
    profile_pictures = load_profile_pictures(employee_info, user_profile_dir)

    # Open webcam for real-time verification
    cap = cv2.VideoCapture(0)
    if not cap.isOpened():
        print("Error: Could not open webcam.")
        return

    # Create fullscreen window
    window_name = 'Real-Time Face Verification'
    cv2.namedWindow(window_name, cv2.WINDOW_NORMAL)
    cv2.setWindowProperty(window_name, cv2.WND_PROP_FULLSCREEN, cv2.WINDOW_FULLSCREEN)

    print("\n--- Verification System Active ---")
    print("Looking for authorized person.")
    print("Controls:")
    print("  - Press 'q' or Ctrl+Q to quit and stop background sync")
    print("  - Press 'r' to reset verification")
    print("  - Press 'f' to toggle fullscreen mode")
    print("System will automatically re-verify when you look at the camera again.")
    
    # Fullscreen state tracking
    is_fullscreen = True
    
    # ========================================================================
    # State Variables for Verification Logic
    # ========================================================================
    frontal_start_time = None          # When frontal face was first detected
    is_frontal_stable = False          # Whether face has been frontal long enough
    verification_done = False          # Whether we've completed a verification
    verification_status = ""           # Text to display (VERIFIED/UNAUTHORIZED)
    verification_color = (255, 255, 255)  # Color for the status text
    last_verification_time = None      # Timestamp of last verification
    matched_employee = None            # Store matched employee info for display
    attendance_log_info = None         # Store attendance log details (type and time)
    
    # Configuration constants
    RE_VERIFICATION_COOLDOWN = 3.0     # Seconds to wait before allowing re-verification
    STABILIZATION_TIME = 1.5           # Seconds face must be stable before verification

    while True:
        # ====================================================================
        # Read Frame from Webcam
        # ====================================================================
        ret, frame = cap.read()
        if not ret:
            break
        
        # Set detector input size to match current frame dimensions
        height, width, _ = frame.shape
        face_detector.setInputSize((width, height))
        
        # ====================================================================
        # Detect Faces using YuNet
        # ====================================================================
        # YuNet returns: [x, y, w, h, landmarks..., confidence]
        _, faces = face_detector.detect(frame)
        
        # ====================================================================
        # CASE 1: Exactly ONE face detected (ideal scenario)
        # ====================================================================
        if faces is not None and len(faces) == 1:
            face_data = faces[0]
            box_xywh = face_data[0:4].astype(int)           # Bounding box
            landmarks = face_data[4:14].reshape((5, 2)).astype(int)  # Facial landmarks
            confidence = face_data[14]                       # Detection confidence

            # Skip low-confidence detections (likely false positives)
            if confidence < 0.9:
                frontal_start_time = None
                is_frontal_stable = False
            else:
                # Default status
                status = "Please Look Forward"
                color = (0, 255, 255)  # Yellow for non-frontal

                # ============================================================
                # Check 1: Is face at the right distance?
                # ============================================================
                is_close, distance_status = is_face_close_enough(box_xywh, width, height)
                
                if not is_close:
                    # Face is too far or too close - reset stabilization timer
                    frontal_start_time = None
                    is_frontal_stable = False
                    
                    # Provide feedback to user
                    if distance_status == "too_far":
                        status = "Move Closer to Camera"
                    elif distance_status == "too_close":
                        status = "Move Back a Little"
                    color = (0, 165, 255)  # Orange for distance issue
                    
                    # Draw feedback on screen
                    if not verification_done:
                        x, y, w, h = box_xywh
                        cv2.rectangle(frame, (x, y), (x + w, y + h), color, 2)
                        cv2.putText(frame, status, (x, y - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.7, color, 2)
                else:
                    # ========================================================
                    # Check 2: Is face frontal (looking at camera)?
                    # ========================================================
                    if is_frontal_face(np.array([landmarks]), width, height):
                        # Face is frontal! Now check if we can verify
                        
                        # Determine if we're allowed to start a new verification
                        # We can verify if:
                        # - No verification done yet, OR
                        # - Cooldown period has passed since last verification
                        can_verify = not verification_done or (
                            last_verification_time is not None and 
                            time.time() - last_verification_time >= RE_VERIFICATION_COOLDOWN
                        )
                        
                        # Start stabilization timer if not already running
                        if frontal_start_time is None and can_verify:
                            frontal_start_time = time.time()
                            if verification_done:
                                print("Re-verification initiated...")
                        
                        # If timer is running, check if stabilization period is complete
                        if frontal_start_time is not None:
                            elapsed_time = time.time() - frontal_start_time
                            
                            # ================================================
                            # VERIFICATION: Stabilization complete!
                            # ================================================
                            if elapsed_time >= STABILIZATION_TIME:
                                is_frontal_stable = True
                                print("Frontal pose stabilized. Verifying...")
                                
                                # Extract face embedding from current frame
                                faces_detected = face_app.get(frame)
                                
                                if len(faces_detected) > 0:
                                    # Get the 512-dimensional embedding vector
                                    current_embedding = faces_detected[0].normed_embedding
                                    
                                    # ========================================
                                    # Calculate Cosine Similarity with ALL embeddings
                                    # ========================================
                                    # Compare against all registered embeddings
                                    # Dot product of normalized vectors = cosine similarity
                                    similarities = np.dot(all_embeddings, current_embedding)
                                    
                                    # Find the best match
                                    max_similarity_idx = np.argmax(similarities)
                                    max_similarity = similarities[max_similarity_idx]
                                    matched_employee = employee_info[max_similarity_idx]
                                    
                                    # Compare similarity against threshold
                                    if max_similarity > 0.6:  # 60% similarity threshold
                                        verification_status = f"VERIFIED: {matched_employee['name']} ({max_similarity:.2f})"
                                        verification_color = (0, 255, 0)  # Green
                                        print(f"✓ Verification successful!")
                                        print(f"  Employee: {matched_employee['name']} ({matched_employee['employee_code']})")
                                        print(f"  Similarity: {max_similarity:.2f}")
                                        
                                        # ========================================
                                        # CHECK ATTENDANCE RESTRICTIONS BEFORE LOGGING
                                        # ========================================
                                        can_log_attendance = True
                                        restriction_message = ""
                                        
                                        if ATTENDANCE_LOGGING_ENABLED and attendance_logger:
                                            try:
                                                employee_db_id = matched_employee.get('db_id')
                                                if employee_db_id:
                                                    # ========================================
                                                    # CHECK 1: Does employee have schedule for today?
                                                    # ========================================
                                                    has_schedule = check_employee_schedule(employee_db_id)
                                                    if not has_schedule:
                                                        can_log_attendance = False
                                                        restriction_message = "no_schedule"  # Mark as no schedule
                                                        verification_status = "No Schedule for Today"
                                                        verification_color = (0, 165, 255)  # Orange
                                                        print(f"  ⚠️  Employee has no schedule for today. Attendance logging denied.")
                                                    
                                                    # ========================================
                                                    # CHECK 2: Has employee already logged out? (if enabled and has schedule)
                                                    # ========================================
                                                    if ENABLE_LOGOUT_RESTRICTION and can_log_attendance:
                                                        # Get today's logs for this employee
                                                        today_logs = attendance_logger.get_today_logs(employee_db_id)
                                                        has_logout = any(log['log_type'] == 'time_out' for log in today_logs)
                                                        if has_logout:
                                                            can_log_attendance = False
                                                            restriction_message = "logout"  # Mark as logout restriction
                                                            verification_status = "Already Logged out"
                                                            verification_color = (0, 165, 255)  # Orange
                                                            print(f"  ⚠️  Employee has already logged out today. No further attendance allowed.")
                                                    
                                                    # ========================================
                                                    # CHECK 3: Is employee in cooldown period? (if enabled and not restricted)
                                                    # ========================================
                                                    if ENABLE_LOGIN_COOLDOWN and can_log_attendance:
                                                        # Get today's logs for this employee (if not already fetched)
                                                        if 'today_logs' not in locals():
                                                            today_logs = attendance_logger.get_today_logs(employee_db_id)
                                                        
                                                        # Find last login
                                                        last_login = None
                                                        for log in reversed(today_logs):  # Check most recent first
                                                            if log['log_type'] == 'time_in':
                                                                last_login = log
                                                                break
                                                        
                                                        if last_login:
                                                            from datetime import datetime, timedelta
                                                            last_login_time = datetime.strptime(last_login['log_time'], '%Y-%m-%d %H:%M:%S')
                                                            time_since_login = (datetime.now() - last_login_time).total_seconds() / 60  # minutes
                                                            
                                                            # Check if cooldown period has passed
                                                            if time_since_login < LOGIN_COOLDOWN_MINUTES:
                                                                can_log_attendance = False
                                                                remaining_minutes = int(LOGIN_COOLDOWN_MINUTES - time_since_login)
                                                                # Calculate the time when cooldown ends
                                                                cooldown_end_time = last_login_time + timedelta(minutes=LOGIN_COOLDOWN_MINUTES)
                                                                restriction_message = cooldown_end_time.strftime('%I:%M %p')
                                                                verification_status = f"VERIFIED - Cooldown ({remaining_minutes}m)"
                                                                verification_color = (0, 200, 200)  # Cyan
                                                                print(f"  ⏳ {LOGIN_COOLDOWN_MINUTES}-minute cooldown active. {remaining_minutes} minutes remaining.")
                                                                print(f"     This prevents accidental logout after login.")
                                            except Exception as e:
                                                print(f"  ⚠️  Error checking attendance restrictions: {e}")
                                        
                                        # ========================================
                                        # LOG ATTENDANCE TO LOCAL DATABASE
                                        # ========================================
                                        if can_log_attendance and ATTENDANCE_LOGGING_ENABLED and attendance_logger:
                                            try:
                                                employee_db_id = matched_employee.get('db_id')
                                                if employee_db_id:
                                                    # ========================================
                                                    # CHECK 4: Determine log type and check for undertime on logout
                                                    # ========================================
                                                    # First determine what type of log will be created
                                                    from datetime import datetime
                                                    
                                                    # Get today's logs if not already fetched
                                                    if 'today_logs' not in locals():
                                                        today_logs = attendance_logger.get_today_logs(employee_db_id)
                                                    
                                                    # Determine if next log will be time_in or time_out
                                                    if len(today_logs) == 0:
                                                        next_log_type = 'time_in'
                                                    else:
                                                        last_log_type = today_logs[-1]['log_type']
                                                        next_log_type = 'time_out' if last_log_type == 'time_in' else 'time_in'
                                                    
                                                    # If attempting to logout, check for undertime and get confirmation
                                                    proceed_with_logging = True
                                                    if next_log_type == 'time_out':
                                                        print(f"  ⏰ User attempting logout - checking for undertime...")
                                                        user_confirmed = check_undertime_and_confirm(employee_db_id)
                                                        if not user_confirmed:
                                                            proceed_with_logging = False
                                                            print(f"  ✗ Logout cancelled by user")
                                                            # Reset verification to allow re-scan
                                                            verification_done = False
                                                            verification_status = ""
                                                            frontal_start_time = None
                                                            is_frontal_stable = False
                                                            last_verification_time = None
                                                            matched_employee = None
                                                            attendance_log_info = None
                                                    
                                                    # Proceed with logging if confirmed (or if it's a time_in)
                                                    if proceed_with_logging:
                                                        # Log attendance without notes parameter to trigger automatic
                                                        # late/on-time/overtime/undertime status calculation
                                                        log_result = attendance_logger.log_attendance(
                                                            employee_db_id=employee_db_id
                                                        )
                                                        if log_result['success']:
                                                            # Store log info for display on screen
                                                            log_datetime = datetime.strptime(log_result['log_time'], '%Y-%m-%d %H:%M:%S')
                                                            log_time_formatted = log_datetime.strftime('%I:%M %p')  # Format as 7:00 AM
                                                            log_type_display = "Login" if log_result['log_type'] == 'time_in' else "Logout"
                                                            
                                                            attendance_log_info = {
                                                                'type': log_type_display,
                                                                'time': log_time_formatted,
                                                                'log_type': log_result['log_type']
                                                            }
                                                            
                                                            print(f"  📝 Attendance logged: {log_result['log_type']} at {log_result['log_time']}")
                                                            if log_result.get('notes'):
                                                                print(f"     {log_result['notes']}")
                                                            print(f"     Similarity: {max_similarity:.2f}")
                                                        else:
                                                            attendance_log_info = None
                                                            print(f"  ⚠️  Failed to log attendance: {log_result['message']}")
                                                else:
                                                    attendance_log_info = None
                                                    print(f"  ⚠️  Cannot log attendance: Employee DB ID not found")
                                            except Exception as e:
                                                attendance_log_info = None
                                                print(f"  ⚠️  Error logging attendance: {e}")
                                        elif not can_log_attendance:
                                            # Store restriction message for display
                                            attendance_log_info = {
                                                'type': 'Re scan after',
                                                'time': restriction_message,
                                                'log_type': 'restricted'
                                            }
                                        # ========================================
                                        
                                    else:
                                        verification_status = f"UNAUTHORIZED ({max_similarity:.2f})"
                                        verification_color = (0, 0, 255)  # Red
                                        matched_employee = None  # Clear matched employee for unauthorized
                                        attendance_log_info = None  # Clear attendance log info
                                        print(f"✗ Verification failed.")
                                        print(f"  Best match: {employee_info[max_similarity_idx]['name']} - Similarity: {max_similarity:.2f}")
                                    
                                    # Mark verification as complete
                                    verification_done = True
                                    last_verification_time = time.time()
                                    is_frontal_stable = False
                                    frontal_start_time = None
                            else:
                                # Still stabilizing - show progress
                                progress = elapsed_time / STABILIZATION_TIME * 100
                                status = f"Stabilizing... {progress:.0f}%"
                                color = (255, 165, 0)  # Orange
                        elif verification_done and not can_verify:
                            # In cooldown period - show countdown
                            remaining_cooldown = RE_VERIFICATION_COOLDOWN - (time.time() - last_verification_time)
                            status = f"{verification_status} (Re-verify in {remaining_cooldown:.0f}s)"
                            color = verification_color
                        elif verification_done:
                            # Show previous verification result
                            status = verification_status
                            color = verification_color
                    else:
                        # ====================================================
                        # Face is NOT frontal - reset stabilization
                        # ====================================================
                        frontal_start_time = None
                        is_frontal_stable = False
                        if not verification_done:
                            status = "Please Look Forward"
                            color = (0, 255, 255)  # Yellow
                        else:
                            # Keep showing previous verification result
                            status = verification_status
                            color = verification_color
                    
                    # Draw bounding box and status on frame
                    x, y, w, h = box_xywh
                    cv2.rectangle(frame, (x, y), (x + w, y + h), color, 2)
                    
                    # Display verification info below the bounding box
                    if verification_done and matched_employee is not None:
                        # Check if user is restricted
                        is_restricted = attendance_log_info and attendance_log_info['log_type'] == 'restricted'
                        
                        # Check if user has no schedule (special case - show message without card)
                        if restriction_message == 'no_schedule':
                            # NO SCHEDULE - Show message at top, no card
                            cv2.putText(frame, "No Schedule for Today", (x, y - 10), 
                                       cv2.FONT_HERSHEY_SIMPLEX, 0.60, (0, 0, 255), 2)  # Red text, bold
                        elif is_restricted:
                            # RESTRICTED USER - Show restriction message at top, no card
                            if attendance_log_info['time'] == 'logout':
                                # User already logged out
                                restriction_text = "Already Logged out"
                            else:
                                # Cooldown restriction - show time
                                restriction_text = f"{attendance_log_info['type']}: {attendance_log_info['time']}"
                            cv2.putText(frame, restriction_text, (x, y - 10), 
                                       cv2.FONT_HERSHEY_SIMPLEX, 0.50, (0, 0, 255), 2)  # Red text, bold, 40% smaller (0.7 -> 0.42)
                        else:
                            # NORMAL USER - Show card with profile photo and attendance info
                            emp_name = matched_employee.get('name', 'Unknown')
                            emp_code = matched_employee.get('employee_code', 'N/A')
                            
                            # Card dimensions - calculate proper size based on text
                            photo_size = 60
                            card_padding = 10
                            text_height = 50
                            card_height = photo_size + (card_padding * 2)
                            
                            # Calculate text width to ensure card fits everything
                            name_size = cv2.getTextSize(emp_name, cv2.FONT_HERSHEY_SIMPLEX, 0.5, 2)[0]
                            id_size = cv2.getTextSize(emp_code, cv2.FONT_HERSHEY_SIMPLEX, 0.45, 1)[0]
                            text_width = max(name_size[0], id_size[0]) + 20
                            
                            card_width = photo_size + text_width + (card_padding * 3)
                            
                            # Position card - ensure it stays within frame bounds
                            card_x = max(10, min(x, width - card_width - 10))  # Keep within frame
                            card_y = min(y + h + 10, height - card_height - 10)  # Keep within frame
                            
                            # Check if card would go out of bounds at the bottom
                            if card_y + card_height > height:
                                card_y = y - card_height - 10  # Place above face box instead
                            
                            # Get pre-loaded profile picture
                            profile_pic = profile_pictures.get(emp_code)
                            
                            # Draw white card background with shadow (no border)
                            shadow_offset = 3
                            cv2.rectangle(frame, (card_x + shadow_offset, card_y + shadow_offset), 
                                         (card_x + card_width + shadow_offset, card_y + card_height + shadow_offset), 
                                         (50, 50, 50), -1)  # Shadow
                            cv2.rectangle(frame, (card_x, card_y), (card_x + card_width, card_y + card_height), 
                                         (255, 255, 255), -1)  # White card (no border)
                            
                            # Profile photo circle (left side of card)
                            photo_x = card_x + card_padding
                            photo_y = card_y + card_padding
                            
                            if profile_pic is not None:
                                try:
                                    # Ensure we don't go out of bounds
                                    if photo_y + photo_size <= height and photo_x + photo_size <= width:
                                        # Resize and create circular mask
                                        profile_resized = cv2.resize(profile_pic, (photo_size, photo_size))
                                        
                                        # Create circular mask
                                        mask = np.zeros((photo_size, photo_size), dtype=np.uint8)
                                        center = (photo_size // 2, photo_size // 2)
                                        cv2.circle(mask, center, photo_size // 2, 255, -1)
                                        
                                        # Extract region and apply circular photo
                                        roi = frame[photo_y:photo_y + photo_size, photo_x:photo_x + photo_size]
                                        for c in range(3):
                                            roi[:, :, c] = np.where(mask == 255, profile_resized[:, :, c], roi[:, :, c])
                                        
                                        # Draw circle border
                                        cv2.circle(frame, (photo_x + photo_size // 2, photo_y + photo_size // 2), 
                                                  photo_size // 2, (200, 200, 200), 2)
                                except Exception as e:
                                    # If image fails, draw default circle
                                    cv2.circle(frame, (photo_x + photo_size // 2, photo_y + photo_size // 2), 
                                              photo_size // 2, (180, 180, 180), -1)
                                    cv2.circle(frame, (photo_x + photo_size // 2, photo_y + photo_size // 2), 
                                              photo_size // 2, (150, 150, 150), 2)
                            else:
                                # Draw default avatar circle
                                cv2.circle(frame, (photo_x + photo_size // 2, photo_y + photo_size // 2), 
                                          photo_size // 2, (180, 180, 180), -1)
                                cv2.circle(frame, (photo_x + photo_size // 2, photo_y + photo_size // 2), 
                                          photo_size // 2, (150, 150, 150), 2)
                            
                            # Text area (right side of card)
                            text_x = photo_x + photo_size + card_padding
                            text_y_name = card_y + 20
                            text_y_id = card_y + 38
                            text_y_log = card_y + 56
                            
                            # Draw employee name (smaller, dark text)
                            cv2.putText(frame, emp_name, (text_x, text_y_name), 
                                       cv2.FONT_HERSHEY_SIMPLEX, 0.45, (50, 50, 50), 1)
                            
                            # Draw employee ID (smaller, gray text)
                            cv2.putText(frame, emp_code, (text_x, text_y_id), 
                                       cv2.FONT_HERSHEY_SIMPLEX, 0.4, (100, 100, 100), 1)
                            
                            # Draw attendance log info (Login/Logout with time) - LARGER AND BOLDER
                            if attendance_log_info is not None:
                                log_text = f"{attendance_log_info['type']}: {attendance_log_info['time']}"
                                # Green for Login, Darker Orange for Logout
                                if attendance_log_info['log_type'] == 'time_in':
                                    log_color = (0, 200, 0)  # Green
                                elif attendance_log_info['log_type'] == 'time_out':
                                    log_color = (0, 100, 200)  # Darker Orange
                                else:
                                    log_color = (100, 100, 100)  # Gray default
                                cv2.putText(frame, log_text, (text_x, text_y_log), 
                                           cv2.FONT_HERSHEY_SIMPLEX, 0.5, log_color, 1)  # Larger (0.6) and bolder (thickness 2)
                            
                            # Draw status text at top of bounding box - always GOOD for non-restricted
                            cv2.putText(frame, "GOOD", (x, y - 10), 
                                       cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 0), 2)
                        
                    elif verification_done and matched_employee is None:
                        # Unauthorized - show message below box
                        y_offset = y + h + 25
                        
                        # Create overlay for background
                        overlay = frame.copy()
                        cv2.rectangle(overlay, (x, y_offset - 20), (x + w, y_offset + 10), 
                                     (0, 0, 0), -1)
                        cv2.addWeighted(overlay, 0.6, frame, 0.4, 0, frame)
                        
                        cv2.putText(frame, "UNAUTHORIZED", (x + 5, y_offset), 
                                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 0, 255), 2)
                    else:
                        # Show status above box (during detection/stabilization)
                        cv2.putText(frame, status, (x, y - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.7, color, 2)
        
        # ====================================================================
        # CASE 2: Multiple faces detected
        # ====================================================================
        elif faces is not None and len(faces) > 1:
            # Reset verification state
            frontal_start_time = None
            is_frontal_stable = False
            
            if not verification_done:
                # Draw red boxes around all detected faces
                for face_data in faces:
                    box_xywh = face_data[0:4].astype(int)
                    x, y, w, h = box_xywh
                    cv2.rectangle(frame, (x, y), (x + w, y + h), (0, 0, 255), 2)
                
                # Display warning message
                cv2.putText(frame, "Multiple Faces Detected!", (20, height - 20), 
                           cv2.FONT_HERSHEY_SIMPLEX, 1.0, (0, 0, 255), 2)
        
        # ====================================================================
        # CASE 3: No face detected
        # ====================================================================
        else:
            # Reset stabilization timer, but keep verification result if exists
            if not verification_done:
                frontal_start_time = None
                is_frontal_stable = False
        
        # ====================================================================
        # Display verification info below bounding box (removed top banner)
        # ====================================================================
        # Info will be displayed in the main face detection loop below the box
        
        # ====================================================================
        # Resize frame to fit screen while maintaining aspect ratio
        # ====================================================================
        if is_fullscreen:
            # Get screen resolution
            screen_width = int(cv2.getWindowImageRect(window_name)[2]) or 1920
            screen_height = int(cv2.getWindowImageRect(window_name)[3]) or 1080
            
            # Calculate aspect ratios
            frame_h, frame_w = frame.shape[:2]
            screen_aspect = screen_width / screen_height
            frame_aspect = frame_w / frame_h
            
            # Resize to fill screen while maintaining aspect ratio
            if frame_aspect > screen_aspect:
                # Frame is wider - fit to width
                new_width = screen_width
                new_height = int(screen_width / frame_aspect)
            else:
                # Frame is taller - fit to height
                new_height = screen_height
                new_width = int(screen_height * frame_aspect)
            
            # Resize frame
            display_frame = cv2.resize(frame, (new_width, new_height))
            
            # Create black canvas of screen size
            canvas = np.zeros((screen_height, screen_width, 3), dtype=np.uint8)
            
            # Center the frame on canvas
            y_offset = (screen_height - new_height) // 2
            x_offset = (screen_width - new_width) // 2
            canvas[y_offset:y_offset+new_height, x_offset:x_offset+new_width] = display_frame
            
            # Show the canvas
            cv2.imshow(window_name, canvas)
        else:
            # Show normal size frame
            cv2.imshow(window_name, frame)

        # ====================================================================
        # Handle Keyboard Input
        # ====================================================================
        key = cv2.waitKey(1) & 0xFF
        
        # Check for Ctrl+Q (works on Windows/Linux/Mac)
        # On Windows, we detect Ctrl key state using cv2.getWindowProperty or key code
        if key == ord('q') or key == 17:  # 'q' or Ctrl+Q
            # Check if Ctrl is pressed (key 17 is Ctrl on some systems)
            # For better Ctrl+Q detection, we'll accept both 'q' and check modifiers
            import platform
            
            # Signal graceful shutdown to stop background sync
            shutdown_signal_file = os.environ.get('KIOSK_SHUTDOWN_SIGNAL')
            if shutdown_signal_file:
                try:
                    # Create signal file to tell start_kiosk.py to stop sync manager
                    with open(shutdown_signal_file, 'w') as f:
                        f.write('shutdown_requested')
                    print("\n🛑 Shutdown signal sent. Stopping all systems...")
                except Exception as e:
                    print(f"Warning: Could not create shutdown signal: {e}")
            
            # Quit the application
            break
        elif key == ord('r'):
            # Manual reset - allows immediate re-verification (bypasses cooldown)
            verification_done = False
            verification_status = ""
            frontal_start_time = None
            is_frontal_stable = False
            last_verification_time = None
            matched_employee = None
            attendance_log_info = None  # Clear attendance log info
            print("Manual verification reset. Ready for immediate verification.")
        elif key == ord('f'):
            # Toggle fullscreen mode
            is_fullscreen = not is_fullscreen
            if is_fullscreen:
                cv2.setWindowProperty(window_name, cv2.WND_PROP_FULLSCREEN, cv2.WINDOW_FULLSCREEN)
                print("Fullscreen mode enabled")
            else:
                cv2.setWindowProperty(window_name, cv2.WND_PROP_FULLSCREEN, cv2.WINDOW_NORMAL)
                print("Fullscreen mode disabled")
            
    # Clean up resources
    cap.release()
    cv2.destroyAllWindows()

# ============================================================================
# PROGRAM ENTRY POINT
# ============================================================================
if __name__ == "__main__":
    # Start the verification system
    run_verification()
