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
import urllib.parse
import urllib.request
import json
import keyboard


# ============================================================================
# MANUAL LOGIN DIALOG
# ============================================================================
class ManualLoginDialog:
    """
    A dialog for manual login when face verification fails.
    """
    def __init__(self, parent, attendance_logger):
        self.result = False
        self.attendance_logger = attendance_logger
        self.employee_data = None
        
        # Create dialog window
        self.dialog = tk.Toplevel(parent)
        self.dialog.title("Manual Verification")
        self.dialog.configure(bg='#ffffff')
        
        # Window configuration
        self.dialog.resizable(False, False)
        self.dialog.attributes('-topmost', True) # Keep on top
        
        # Removes title bar for cleaner look (optional, keeping it for drag ability)
        # self.dialog.overrideredirect(True) 
        
        # Center the window
        window_width = 500
        window_height = 400
        screen_width = self.dialog.winfo_screenwidth()
        screen_height = self.dialog.winfo_screenheight()
        x = (screen_width - window_width) // 2
        y = (screen_height - window_height) // 2
        self.dialog.geometry(f"{window_width}x{window_height}+{x}+{y}")
        
        # Main Container
        main_frame = tk.Frame(self.dialog, bg='#ffffff', padx=40, pady=30)
        main_frame.pack(fill='both', expand=True)
        
        # Error Message (Red)
        error_label = tk.Label(main_frame, text="Too many attempts please log in manually", 
                              font=("Segoe UI", 12), fg="#dc3545", bg='#ffffff', pady=10)
        error_label.pack()
        
        # ID Number Field
        tk.Label(main_frame, text="ID Number", font=("Segoe UI", 10, "bold"), 
                bg='#ffffff', fg="#555555", anchor='w').pack(fill='x', pady=(20, 5))
        
        self.id_entry = tk.Entry(main_frame, font=("Segoe UI", 12), bg="#f8f9fa", 
                                relief="flat", highlightthickness=1, highlightbackground="#ced4da")
        self.id_entry.pack(fill='x', ipady=8, pady=(0, 15))
        
        # Password Field
        tk.Label(main_frame, text="Password", font=("Segoe UI", 10, "bold"), 
                bg='#ffffff', fg="#555555", anchor='w').pack(fill='x', pady=(5, 0))
        
        self.pass_entry = tk.Entry(main_frame, font=("Segoe UI", 12), bg="#f8f9fa", show="*",
                                  relief="flat", highlightthickness=1, highlightbackground="#ced4da")
        self.pass_entry.pack(fill='x', ipady=8, pady=(0, 20))
        
        # Login Button
        self.login_btn = tk.Button(main_frame, text="Log in", font=("Segoe UI", 12, "bold"),
                                  bg="#198754", fg="white", activebackground="#157347", activeforeground="white",
                                  relief="flat", cursor="hand2", command=self.verify_login)
        self.login_btn.pack(fill='x', ipady=5)
        
        # Bind enter key
        self.dialog.bind('<Return>', lambda e: self.verify_login())
        
        # Focus on ID
        self.id_entry.focus_set()
        
        # Make modal
        self.dialog.transient(parent)
        self.dialog.grab_set()
        parent.wait_window(self.dialog)
        
    def verify_login(self):
        emp_id = self.id_entry.get().strip()
        password = self.pass_entry.get()
        
        if not emp_id or not password:
            messagebox.showerror("Error", "Please enter both ID and Password", parent=self.dialog)
            return
            
        # UI Feedback
        self.login_btn.config(text="Verifying...", state="disabled")
        self.dialog.update()
        
        try:
            # 1. Verify against PHP Backend
            url = "http://localhost/EndDev/login/auth.php?action=login"
            data = urllib.parse.urlencode({
                'employee_id': emp_id,
                'password': password
            }).encode('utf-8')
            
            req = urllib.request.Request(url, data=data)
            with urllib.request.urlopen(req) as response:
                result = json.loads(response.read().decode('utf-8'))
                
            if result.get('success'):
                # Login Successful
                user_info = result.get('user', {})
                employee_code = user_info.get('employee_id')
                
                # 2. Get Local DB ID for logging
                # We need the LOCAL db_id to log attendance in local sqlite
                local_emp = self.attendance_logger.get_employee_by_code(employee_code)
                
                if local_emp:
                    self.employee_data = local_emp
                    self.result = True
                    self.dialog.destroy()
                else:
                    # Sync issue? Employee exists in server but not local
                    messagebox.showerror("Sync Error", "Employee verified but not found in local database. Please sync data.", parent=self.dialog)
                    self.login_btn.config(text="Log in", state="normal")
            else:
                messagebox.showerror("Login Failed", result.get('error', "Invalid credentials"), parent=self.dialog)
                self.login_btn.config(text="Log in", state="normal")
                
        except Exception as e:
            print(f"Manual Login Error: {e}")
            messagebox.showerror("Connection Error", f"Could not connect to server: {e}", parent=self.dialog)
            self.login_btn.config(text="Log in", state="normal")


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
    print(f"✓ Loaded {len(profile_pics)} profile pictures into memory.")
    return profile_pics

# ============================================================================
# KIOSK UI HELPERS
# ============================================================================

# Brand Colors (OpenCV uses BGR)
COLOR_PRIMARY_DARK = (62, 77, 27)      # #1b4d3e (Dark Green)
COLOR_TEXT_WHITE = (255, 255, 255)
COLOR_ACCENT_GREEN = (50, 205, 50)     # Lime Green
COLOR_ACCENT_RED = (0, 0, 255)         # Red
COLOR_CARD_BG = (255, 255, 255)        # White

# Dimensions (will be scaled relative to screen size)
HEADER_HEIGHT_RATIO = 0.12  # 12% of screen height
FOOTER_HEIGHT_RATIO = 0.08  # 8% of screen height

def draw_kiosk_header(canvas, width, height, logo_img=None):
    """Draws the branded header with logo and title."""
    header_h = int(height * HEADER_HEIGHT_RATIO)
    
    # Background
    cv2.rectangle(canvas, (0, 0), (width, header_h), COLOR_PRIMARY_DARK, -1)
    
    # Logo Logic
    logo_size = int(header_h * 0.85) # 85% of header height
    logo_y = (header_h - logo_size) // 2
    
    # Title Text
    title_text = "Automated Face Recognition Attendance System"
    # Dynamic font scale
    font_scale = height / 1000.0 * 1.0
    text_size = cv2.getTextSize(title_text, cv2.FONT_HERSHEY_SIMPLEX, font_scale, 2)[0]
    
    # Calculate group positioning (Logo + Padding + Text) to center everything
    padding = 20
    total_width = logo_size + padding + text_size[0]
    
    # Starting X for the group
    start_x = (width - total_width) // 2
    
    logo_x = start_x
    
    if logo_img is not None:
        try:
            # Resize logo to fit height while maintaining aspect ratio
            h, w = logo_img.shape[:2]
            scale = logo_size / h
            new_w = int(w * scale)
            new_h = int(h * scale)
            
            # Recalculate logo_x to center the logo within its allocate square slot 
            # or just align left? Let's align left of the group.
            
            logo_resized = cv2.resize(logo_img, (new_w, new_h))
            
            # Simple alpha blending if 4 channels
            y1, y2 = logo_y, logo_y + new_h
            x1, x2 = logo_x, logo_x + new_w
            
            # Ensure within bounds
            if x2 <= width and y2 <= height:
                if logo_resized.shape[2] == 4:
                    alpha_s = logo_resized[:, :, 3] / 255.0
                    alpha_l = 1.0 - alpha_s
                    
                    for c in range(0, 3):
                        canvas[y1:y2, x1:x2, c] = (alpha_s * logo_resized[:, :, c] + 
                                                 alpha_l * canvas[y1:y2, x1:x2, c])
                else:
                    canvas[y1:y2, x1:x2] = logo_resized
        except Exception as e:
            print(f"Error drawing logo: {e}")
            # Fallback circle
            cv2.circle(canvas, (logo_x + logo_size//2, header_h//2), logo_size//2, (212, 169, 83), -1)
            cv2.putText(canvas, "LOGO", (logo_x, header_h//2), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0,0,0), 1)
    else:
        # Placeholder
        cv2.circle(canvas, (logo_x + logo_size//2, header_h//2), logo_size//2, (212, 169, 83), -1)
        cv2.putText(canvas, "LOGO", (logo_x, header_h//2), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0,0,0), 1)
               
    
    text_x = logo_x + logo_size + padding
    # Vertical center
    text_y = header_h // 2 + (text_size[1] // 2)
    
    cv2.putText(canvas, title_text, (text_x, text_y), 
               cv2.FONT_HERSHEY_SIMPLEX, font_scale, COLOR_TEXT_WHITE, 2)

def draw_kiosk_footer(canvas, width, height):
    """Draws the footer with active date/time."""
    footer_h = int(height * FOOTER_HEIGHT_RATIO)
    footer_y = height - footer_h
    
    # Background
    cv2.rectangle(canvas, (0, footer_y), (width, height), COLOR_PRIMARY_DARK, -1)
    
    # Date/Time Text
    from datetime import datetime
    now = datetime.now()
    # Format: December 15, 2025 Monday | 8:00:00 AM
    time_str = now.strftime("%B %d, %Y %A | %I:%M:%S %p")
    
    font_scale = height / 1000.0 * 0.8
    text_size = cv2.getTextSize(time_str, cv2.FONT_HERSHEY_SIMPLEX, font_scale, 2)[0]
    
    text_x = (width - text_size[0]) // 2
    text_y = footer_y + (footer_h + text_size[1]) // 2
    
    cv2.putText(canvas, time_str, (text_x, text_y), 
               cv2.FONT_HERSHEY_SIMPLEX, font_scale, COLOR_TEXT_WHITE, 2)

def map_coord(val, scale, offset):
    """Helper to map frame coordinate to canvas coordinate"""
    return int(val * scale + offset)

def run_verification():
    """
    Run the real-time face verification system.
    """
    # ... (skipping database loading logic) ...
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
        
        authorized_embedding = np.load(AUTHORIZED_EMBEDDING_PATH)
        all_embeddings = authorized_embedding.reshape(1, -1)
        employee_ids = [0]
        employee_info = [{'db_id': 0, 'employee_code': 'ENROLLED', 'name': 'Authorized Person'}]
        print("Using single-person enrollment mode")

    # Pre-load all profile pictures to avoid disk I/O in the loop
    user_profile_dir = os.path.join(script_dir, "database", "user_profile")
    profile_pictures = load_profile_pictures(employee_info, user_profile_dir)
    
    # Load Kiosk Logo
    logo_path = os.path.join(script_dir, "bpc-logo.png")
    logo_img = None
    if os.path.exists(logo_path):
        logo_img = cv2.imread(logo_path, cv2.IMREAD_UNCHANGED)
        print(f"✓ Kiosk logo loaded: {logo_path}")
    else:
        print(f"⚠️ Warning: Kiosk logo not found at {logo_path}")

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
    consecutive_failures = 0           # Track failed verification attempts
    is_manual_source = False           # Track if verification was manual
    last_simulation_time = 0           # Debounce for simulated failure
    
    # Configuration constants
    RE_VERIFICATION_COOLDOWN = 3.0     # Seconds to wait before allowing re-verification
    STABILIZATION_TIME = 1.5           # Seconds face must be stable before verification
    
    # Initialize Main Tkinter Root for Dialogs
    try:
        root = tk.Tk()
        root.withdraw() # Hide the main window
    except Exception as e:
        print(f"Warning: Could not initialize Tkinter root: {e}")
        root = None

    while True:
        # ====================================================================
        # Read Frame from Webcam
        # ====================================================================
        ret, frame = cap.read()
        if not ret:
            break

        # SIMULATE FAILURE HOTKEY (Alt + Shift + Delete)
        if keyboard.is_pressed('alt+shift+delete'):
            current_time = time.time()
            if current_time - last_simulation_time > 1.0: # 1 second debounce
                consecutive_failures += 1
                last_simulation_time = current_time
                print(f"⚠️  SIMULATED FAILURE TRIGGERED! (Total Failures: {consecutive_failures})")
                
                # Visual feedback on console
                if consecutive_failures >= 3:
                     print("   -> Threshold reached! Manual login should trigger next.")
            
        # ====================================================================
        # UI PREPARATION: Create Canvas and Header/Footer
        # ====================================================================
        # Get screen dims
        if is_fullscreen:
            try:
                # Try to get actual window size if possible, else default
                rect = cv2.getWindowImageRect(window_name)
                screen_width = int(rect[2]) if rect[2] > 0 else 1920
                screen_height = int(rect[3]) if rect[3] > 0 else 1080
            except:
                screen_width = 1920
                screen_height = 1080
        else:
            screen_width = 1280
            screen_height = 720
            
        # Create Main Canvas
        canvas = np.zeros((screen_height, screen_width, 3), dtype=np.uint8)
        
        # Draw Header & Footer
        draw_kiosk_header(canvas, screen_width, screen_height, logo_img)
        draw_kiosk_footer(canvas, screen_width, screen_height)
        
        # Calculate Content Area (Space between header and footer)
        header_h = int(screen_height * HEADER_HEIGHT_RATIO)
        footer_h = int(screen_height * FOOTER_HEIGHT_RATIO)
        
        content_y = header_h
        content_h = screen_height - header_h - footer_h
        content_w = screen_width
        
        # ====================================================================
        # Process Video Frame
        # ====================================================================
        frame_h, frame_w = frame.shape[:2]
        
        # Calculate scaling to fit content area while maintaining aspect ratio
        scale_w = content_w / frame_w
        scale_h = content_h / frame_h
        scale = min(scale_w, scale_h)
        
        new_w = int(frame_w * scale)
        new_h = int(frame_h * scale)
        
        # Centering offsets
        off_x = (content_w - new_w) // 2
        off_y = content_y + (content_h - new_h) // 2
        
        # Resize frame
        resized_frame = cv2.resize(frame, (new_w, new_h))
        
        # Place frame on canvas
        canvas[off_y:off_y+new_h, off_x:off_x+new_w] = resized_frame
        
        # Set detector input size using ORIGINAL frame size
        face_detector.setInputSize((frame_w, frame_h))
        _, faces = face_detector.detect(frame)
        
        # ====================================================================
        # Detect Faces using YuNet
        # ====================================================================
        # Note: 'faces' coordinates are in ORIGINAL frame space.
        # We must map them to CANVAS space for drawing.
        
        if faces is not None and len(faces) == 1:
            face_data = faces[0]
            # Coords in frame space
            fx, fy, fw, fh = face_data[0:4].astype(int)
            landmarks = face_data[4:14].reshape((5, 2)).astype(int)
            confidence = face_data[14]

            # Map to Canvas Space
            cx = map_coord(fx, scale, off_x)
            cy = map_coord(fy, scale, off_y)
            cw = int(fw * scale)
            ch = int(fh * scale)
            box_canvas = (cx, cy, cw, ch)
            
            # --- FALLBACK: CHECK FOR MANUAL LOGIN TRIGGER ---
            # Triggers if failures >= 3 AND we are not already verifying
            # Placing this here ensures it triggers as soon as a face is detected
            manual_login_user = None
            if consecutive_failures >= 3:
                # Trigger Manual Login
                if root is None:
                    try: 
                        root = tk.Tk(); root.withdraw()
                    except: pass
                
                if root:
                    # Update root to handle events
                    root.update()
                    
                    login_dialog = ManualLoginDialog(root, attendance_logger)
                if login_dialog.result:
                    manual_login_user = login_dialog.employee_data
                    consecutive_failures = 0
                else:
                    # User cancelled
                    consecutive_failures = 0 # Reset or keep? Resetting allows retry.

            if confidence < 0.9 and not manual_login_user:
                frontal_start_time = None
                is_frontal_stable = False
            else:
                status = "Please Look Forward"
                color = (0, 255, 255)  # Yellow

                is_close, distance_status = is_face_close_enough([fx, fy, fw, fh], frame_w, frame_h)
                
                if not is_close:
                    frontal_start_time = None
                    is_frontal_stable = False
                    if distance_status == "too_far": status = "Move Closer"
                    elif distance_status == "too_close": status = "Move Back"
                    color = (0, 165, 255)
                    
                    if not verification_done:
                        cv2.rectangle(canvas, (cx, cy), (cx + cw, cy + ch), color, 2)
                        # Centered text above box
                        text_size = cv2.getTextSize(status, cv2.FONT_HERSHEY_SIMPLEX, 0.8, 2)[0]
                        text_x = cx + (cw - text_size[0]) // 2
                        cv2.putText(canvas, status, (text_x, cy - 20), cv2.FONT_HERSHEY_SIMPLEX, 0.8, color, 2)
                else:
                    if is_frontal_face(np.array([landmarks]), frame_w, frame_h) or manual_login_user:
                        # ... (Verification Logic Same as before) ...
                        can_verify = not verification_done or (
                            last_verification_time is not None and 
                            time.time() - last_verification_time >= RE_VERIFICATION_COOLDOWN
                        )
                        
                        if (frontal_start_time is None and can_verify) or manual_login_user:
                            if not manual_login_user:
                                frontal_start_time = time.time()
                                if verification_done: print("Re-verification initiated...")
                            else:
                                # Force verification immediately for manual login
                                frontal_start_time = time.time() - STABILIZATION_TIME - 1.0
                        
                        if frontal_start_time is not None:
                            elapsed_time = time.time() - frontal_start_time
                            
                            if elapsed_time >= STABILIZATION_TIME or manual_login_user:
                                is_frontal_stable = True
                                
                                if not manual_login_user:
                                    faces_detected = face_app.get(frame)
                                    if len(faces_detected) > 0:
                                        current_embedding = faces_detected[0].normed_embedding
                                        similarities = np.dot(all_embeddings, current_embedding)
                                        max_similarity_idx = np.argmax(similarities)
                                        max_similarity = similarities[max_similarity_idx]
                                        matched_employee = employee_info[max_similarity_idx]
                                    else:
                                        max_similarity = 0.0
                                else:
                                    max_similarity = 1.0 # Mock value for logic flow
                                    matched_employee = manual_login_user
                                
                                # Logic for success or fail
                                if (max_similarity > 0.6) or manual_login_user:
                                    if manual_login_user:
                                        is_manual_source = True
                                        # matched_employee already set
                                    else:
                                        consecutive_failures = 0
                                        is_manual_source = False
                                    
                                    # ... Enter Success Block ...
                                    verification_color = (50, 205, 50) # Lime Green
                                        
                                    # --- ATTENDANCE LOGIC COPY START ---
                                    can_log_attendance = True
                                    restriction_message = ""
                                    manual_log_type = None
                                    
                                    if ATTENDANCE_LOGGING_ENABLED and attendance_logger:
                                        try:
                                            employee_db_id = matched_employee.get('db_id')
                                            if employee_db_id:
                                                has_schedule = check_employee_schedule(employee_db_id)
                                                if not has_schedule:
                                                    # Instead of restricting, treat as VISIT
                                                    manual_log_type = 'visit'
                                                    verification_color = (255, 0, 255) # Magenta/Purple for Visit
                                                    print(f"  ℹ️  No schedule found. Logging as VISIT.")
                                                
                                                # Only check usual restrictions if it's NOT a visit
                                                if manual_log_type != 'visit':
                                                    if ENABLE_LOGOUT_RESTRICTION and can_log_attendance:
                                                        today_logs = attendance_logger.get_today_logs(employee_db_id)
                                                        has_logout = any(log['log_type'] == 'time_out' for log in today_logs)
                                                        if has_logout:
                                                            can_log_attendance = False
                                                            restriction_message = "logout"
                                                            verification_color = (0, 165, 255)

                                                    if ENABLE_LOGIN_COOLDOWN and can_log_attendance:
                                                        if 'today_logs' not in locals():
                                                            today_logs = attendance_logger.get_today_logs(employee_db_id)
                                                        last_login = None
                                                        for log in reversed(today_logs):
                                                            if log['log_type'] == 'time_in':
                                                                last_login = log
                                                                break
                                                        if last_login:
                                                            from datetime import datetime, timedelta
                                                            last_login_time = datetime.strptime(last_login['log_time'], '%Y-%m-%d %H:%M:%S')
                                                            time_since_login = (datetime.now() - last_login_time).total_seconds() / 60
                                                            if time_since_login < LOGIN_COOLDOWN_MINUTES:
                                                                can_log_attendance = False
                                                                cooldown_end_time = last_login_time + timedelta(minutes=LOGIN_COOLDOWN_MINUTES)
                                                                restriction_message = cooldown_end_time.strftime('%I:%M %p')
                                                                verification_color = (0, 200, 200)

                                        except Exception as e:
                                            print(f"Error checking restrictions: {e}")
                                    
                                    if can_log_attendance and ATTENDANCE_LOGGING_ENABLED and attendance_logger:
                                        try:
                                            employee_db_id = matched_employee.get('db_id')
                                            if 'today_logs' not in locals(): today_logs = attendance_logger.get_today_logs(employee_db_id)
                                            
                                            # Determine next log type if not already set (i.e. not 'visit')
                                            if manual_log_type:
                                                next_log_type = manual_log_type
                                            else:
                                                if len(today_logs) == 0: next_log_type = 'time_in'
                                                else: next_log_type = 'time_out' if today_logs[-1]['log_type'] == 'time_in' else 'time_in'
                                            
                                            proceed_with_logging = True
                                            
                                            # Optional: Confirm undertime for Time Out (skipped in Kiosk for flow)
                                            if next_log_type == 'time_out':
                                                 # user_confirmed = check_undertime_and_confirm(employee_db_id)
                                                 pass

                                            if proceed_with_logging:
                                                # LOG THE ATTENDANCE
                                                source = 'manual login' if is_manual_source else 'webcam'
                                                log_result = attendance_logger.log_attendance(employee_db_id=employee_db_id, log_type=manual_log_type, source=source)
                                                
                                                if log_result['success']:
                                                    log_datetime = datetime.strptime(log_result['log_time'], '%Y-%m-%d %H:%M:%S')
                                                    log_time_formatted = log_datetime.strftime('%I:%M %p')
                                                    
                                                    # Set display strings
                                                    raw_log_type = log_result['log_type']
                                                    if raw_log_type == 'time_in':
                                                        log_type_display = "Time in"
                                                        verification_color = (50, 205, 50) # Green
                                                    elif raw_log_type == 'time_out':
                                                        log_type_display = "Time out"
                                                        verification_color = (0, 140, 255) # Orange/Gold-ish
                                                    elif raw_log_type == 'visit':
                                                        log_type_display = "Visit"
                                                        verification_color = (255, 0, 255) # Magenta
                                                    else:
                                                        log_type_display = raw_log_type.title()
                                                    
                                                    attendance_log_info = {
                                                        'type': log_type_display,
                                                        'time': log_time_formatted,
                                                        'log_type': raw_log_type
                                                    }
                                                    
                                                    # Update critical status text for the UI
                                                    verification_status = f"Verified - {log_type_display}"
                                                else:
                                                    attendance_log_info = None
                                        except Exception as e:
                                            print(f"Logging error: {e}")
                                            attendance_log_info = None

                                        except Exception:
                                            attendance_log_info = None
                                    elif not can_log_attendance:
                                        attendance_log_info = {
                                             'type': 'Restricted',
                                             'time': restriction_message,
                                             'log_type': 'restricted'
                                        }
                                    # --- ATTENDANCE LOGIC COPY END ---

                                else:
                                    verification_color = (0, 0, 255) # Red
                                    matched_employee = None
                                    attendance_log_info = None
                                
                                verification_done = True
                                last_verification_time = time.time()
                                is_frontal_stable = False
                                frontal_start_time = None
                            else:
                                color = (255, 165, 0)
                                status = "Scanning..."
                        elif verification_done and not can_verify:
                             color = verification_color
                             status = "Verified"
                        elif verification_done:
                             color = verification_color
                             status = "Verified"

                    else:
                        frontal_start_time = None
                        is_frontal_stable = False
                        if not verification_done:
                            status = "Look Straight at Camera" # More polished text
                            color = (0, 255, 255)
                        else:
                            color = verification_color
                            status = "Verified"

                    # DRAW UI OVERLAYS
                    # Draw Face Box
                    cv2.rectangle(canvas, (cx, cy), (cx + cw, cy + ch), color, 3) 
                    
                    # Draw Status Text (Centered above box, Large Green)
                    # "Look straight at the camera" style
                    if not verification_done or (verification_done and not can_verify):
                        display_status = status
                        if "Look" in status: display_color = (50, 255, 50) # Bright Green
                        else: display_color = color
                        
                        text_scale = 1.0
                        text_size = cv2.getTextSize(display_status, cv2.FONT_HERSHEY_SIMPLEX, text_scale, 2)[0]
                        text_x = cx + (cw - text_size[0]) // 2
                        cv2.putText(canvas, display_status, (text_x, cy - 25), cv2.FONT_HERSHEY_SIMPLEX, text_scale, display_color, 2)

                    # VERIFICATION CARD
                    if verification_done and matched_employee is not None:
                        # Card dimensions
                        card_w = 400
                        card_h = 140
                        
                        # Position: Centered horizontally, overlapping bottom of video/footer area
                        # Or just below face? Mockup shows below face.
                        # Let's put it below face, or pinned to bottom of content area if face is low.
                        
                        card_x = cx + (cw - card_w) // 2
                        # Clamp horizontally
                        card_x = max(10, min(card_x, screen_width - card_w - 10))
                        
                        card_y = cy + ch + 30
                        # Clamp vertically (don't overlap footer too much)
                        if card_y + card_h > (screen_height - footer_h):
                            card_y = (screen_height - footer_h) - card_h - 20
                            
                        # Draw Card Background (Rounded logic simplified to basic rect for OpenCV)
                        # Shadow
                        cv2.rectangle(canvas, (card_x+5, card_y+5), (card_x+card_w+5, card_y+card_h+5), (50,50,50), -1)
                        # Main Body (White)
                        cv2.rectangle(canvas, (card_x, card_y), (card_x+card_w, card_y+card_h), COLOR_CARD_BG, -1)
                        
                        # Profile Picture
                        pic_size = 100
                        pic_x = card_x + 20
                        pic_y = card_y + (card_h - pic_size) // 2
                        
                        emp_code = matched_employee.get('employee_code', '')
                        profile_pic = profile_pictures.get(emp_code)
                        
                        # Draw Pic Circle
                        if profile_pic is not None:
                            try:
                                profile_resized = cv2.resize(profile_pic, (pic_size, pic_size))
                                # Circular mask hack
                                mask = np.zeros((pic_size, pic_size), dtype=np.uint8)
                                cv2.circle(mask, (pic_size//2, pic_size//2), pic_size//2, 255, -1)
                                
                                # ROI on canvas
                                roi = canvas[pic_y:pic_y+pic_size, pic_x:pic_x+pic_size]
                                
                                # Blit
                                for c in range(3):
                                    roi[:, :, c] = np.where(mask == 255, profile_resized[:, :, c], roi[:, :, c])
                            except:
                                cv2.circle(canvas, (pic_x+pic_size//2, pic_y+pic_size//2), pic_size//2, (200,200,200), -1)
                        else:
                            cv2.circle(canvas, (pic_x+pic_size//2, pic_y+pic_size//2), pic_size//2, (200,200,200), -1)
                            
                        # Text Info
                        text_x_start = pic_x + pic_size + 20
                        text_y_center = card_y + card_h // 2
                        
                        # Name
                        name = matched_employee.get('name', 'Unknown')
                        cv2.putText(canvas, name, (text_x_start, text_y_center - 10), 
                                   cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0,0,0), 2)
                                   
                        # ID
                        cv2.putText(canvas, emp_code, (text_x_start, text_y_center + 20), 
                                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, (80,80,80), 1)
                                   
                        # Restriction/Log Status (if any)
                        if attendance_log_info:
                             status_text = f"{attendance_log_info['type']}: {attendance_log_info['time']}"
                             
                             # Color mapping based on log type
                             l_type = attendance_log_info['log_type']
                             if l_type == 'time_in': color_s = (0, 150, 0)       # Green
                             elif l_type == 'time_out': color_s = (0, 100, 200)  # Orange
                             elif l_type == 'visit': color_s = (200, 0, 200)     # Purple
                             elif l_type == 'restricted': color_s = (0, 0, 255)  # Red
                             else: color_s = (100, 100, 100)                     # Gray
                             
                             cv2.putText(canvas, status_text, (text_x_start, text_y_center + 50),
                                        cv2.FONT_HERSHEY_SIMPLEX, 0.5, color_s, 1)

                    elif verification_done and matched_employee is None:
                        # UNAUTHORIZED
                         cv2.putText(canvas, "UNAUTHORIZED", (cx, cy + ch + 40), 
                                   cv2.FONT_HERSHEY_SIMPLEX, 1.0, (0, 0, 255), 3)

        elif faces is not None and len(faces) > 1:
            # Multiple faces
             cv2.putText(canvas, "Multiple Faces Detected", (screen_width//2 - 200, screen_height - footer_h - 50), 
                        cv2.FONT_HERSHEY_SIMPLEX, 1.2, (0, 0, 255), 3)

        # Show Canvas
        cv2.imshow(window_name, canvas)
        
        # Keyboard
        key = cv2.waitKey(1) & 0xFF
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
