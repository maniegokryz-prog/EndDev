"""
Real-Time Face Verification System using AuraFace
Hybrid Architecture: OpenCV High GUI (Video) + Tkinter (Dialogs)
"""

import cv2
import numpy as np
import os
import time
import sys
from insightface.app import FaceAnalysis
import tkinter as tk
from tkinter import messagebox
import urllib.parse
import urllib.request
import json
import keyboard
from PIL import Image, ImageTk 
from datetime import datetime

# Import Helper Modules
script_dir = os.path.dirname(os.path.abspath(__file__))
sys.path.append(script_dir)
from attendance_logger import AttendanceLogger
# init_local_db not needed/doesn't exist as function. get_db_connection used locally if needed.
from liveness_detector import LivenessDetector 

# Constants
AUTHORIZED_EMBEDDINGS_PATH = os.path.join(script_dir, "database", "authorized_embeddings.npy")
ATTENDANCE_LOGGING_ENABLED = True
ENABLE_LIVENESS_CHECK = True
LIVENESS_THRESHOLD = 0.5
ENABLE_LOGOUT_RESTRICTION = True
ENABLE_LOGIN_COOLDOWN = True
LOGIN_COOLDOWN_MINUTES = 5
ENABLE_SINGLE_SESSION = True      # Block re-entry after Time Out
MIN_WORK_DURATION_MINUTES = 60    # Min minutes before Time Out allowed

# Brand Colors (OpenCV uses BGR)
COLOR_PRIMARY_DARK = (62, 77, 27)      # #1b4d3e (Dark Green)
COLOR_TEXT_WHITE = (255, 255, 255)
COLOR_ACCENT_GREEN = (50, 205, 50)     # Lime Green

# Dimensions
HEADER_HEIGHT_RATIO = 0.12
FOOTER_HEIGHT_RATIO = 0.08
COLOR_CARD_BG = (255, 255, 255)

# ============================================================================
# HELPER FUNCTIONS
# ============================================================================
def check_employee_schedule(employee_db_id):
    """Check if employee has a schedule for today."""
    try:
        from database.init_local_db import get_db_connection
        conn = get_db_connection()
        cursor = conn.cursor()
        
        day_of_week = datetime.now().weekday()
        today_str = datetime.now().strftime('%Y-%m-%d')
        
        cursor.execute("""
            SELECT 1
            FROM employee_schedules es
            JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
            WHERE es.employee_id = ?
              AND es.is_active = 1
              AND sp.day_of_week = ?
              AND sp.is_active = 1
              AND (es.end_date IS NULL OR es.end_date >= ?)
            LIMIT 1
        """, (employee_db_id, day_of_week, today_str))
        
        result = cursor.fetchone()
        conn.close()
        
        return result is not None
    except Exception as e:
        print(f"Error checking schedule: {e}")
        return True 

def is_frontal_face(landmarks, frame_w, frame_h):
    """Check if face is frontal using landmarks."""
    left_eye = landmarks[0]
    right_eye = landmarks[1]
    nose = landmarks[2]
    
    eye_dist = np.linalg.norm(left_eye - right_eye)
    mid_point_x = (left_eye[0] + right_eye[0]) / 2
    nose_offset_x = abs(nose[0] - mid_point_x)
    
    dy = right_eye[1] - left_eye[1]
    dx = right_eye[0] - left_eye[0]
    angle = np.degrees(np.arctan2(dy, dx))
    
    is_centered_horizontally = nose_offset_x < (eye_dist * 0.2) 
    is_level = abs(angle) < 10
    
    # Approx vertical check (Nose should be below eyes)
    is_nose_below_eyes = nose[1] > left_eye[1] and nose[1] > right_eye[1]
    
    return is_centered_horizontally and is_level and is_nose_below_eyes

def is_face_close_enough(box, frame_w, frame_h):
    """Check if face is within optimal size range."""
    w = box[2]
    ratio = w / frame_w
    
    min_ratio = 0.20
    max_ratio = 0.45
    
    if ratio < min_ratio: return False, "too_far"
    elif ratio > max_ratio: return False, "too_close"
    return True, "optimal"

def load_profile_pictures(employee_info, user_profile_dir):
    """Load profile pics into memory."""
    pics = {}
    if not os.path.exists(user_profile_dir): return pics
    
    for emp in employee_info:
        code = emp.get('employee_code')
        if not code: continue
        for ext in ['.jpg', '.png', '.jpeg']:
            p = os.path.join(user_profile_dir, f"{code}{ext}")
            if os.path.exists(p):
                img = cv2.imread(p)
                if img is not None:
                    pics[code] = img
                break
    return pics

# ============================================================================
# UI DRAWING
# ============================================================================
def draw_kiosk_header(canvas, width, height, logo_img=None):
    header_h = int(height * HEADER_HEIGHT_RATIO)
    cv2.rectangle(canvas, (0, 0), (width, header_h), COLOR_PRIMARY_DARK, -1)
    
    logo_size = int(header_h * 0.85)
    padding = 20
    text = "Automated Face Recognition Attendance System"
    font_scale = height / 1000.0 * 1.0
    text_size = cv2.getTextSize(text, cv2.FONT_HERSHEY_SIMPLEX, font_scale, 2)[0]
    
    total_w = logo_size + padding + text_size[0]
    start_x = (width - total_w) // 2
    
    logo_x = start_x
    logo_y = (header_h - logo_size) // 2
    
    if logo_img is not None:
        try:
             h_l, w_l = logo_img.shape[:2]
             scale = logo_size / h_l
             new_w, new_h = int(w_l * scale), int(h_l * scale)
             resized = cv2.resize(logo_img, (new_w, new_h))
             
             y1, y2 = logo_y, logo_y + new_h
             x1, x2 = logo_x, logo_x + new_w
             if logo_img.shape[2] == 4: # Alpha
                  alpha_s = resized[:, :, 3] / 255.0
                  alpha_l = 1.0 - alpha_s
                  for c in range(0, 3):
                      canvas[y1:y2, x1:x2, c] = (alpha_s * resized[:, :, c] + alpha_l * canvas[y1:y2, x1:x2, c])
             else:
                  canvas[y1:y2, x1:x2] = resized
        except: pass
    else:
        cv2.circle(canvas, (logo_x+logo_size//2, header_h//2), logo_size//2, (200, 200, 200), -1)
        
    text_x = logo_x + logo_size + padding
    text_y = header_h//2 + text_size[1]//2
    cv2.putText(canvas, text, (text_x, text_y), cv2.FONT_HERSHEY_SIMPLEX, font_scale, COLOR_TEXT_WHITE, 2)

def draw_kiosk_footer(canvas, width, height):
    footer_h = int(height * FOOTER_HEIGHT_RATIO)
    y = height - footer_h
    cv2.rectangle(canvas, (0, y), (width, height), COLOR_PRIMARY_DARK, -1)
    
    now = datetime.now()
    t_str = now.strftime("%B %d, %Y %A | %I:%M:%S %p")
    font_scale = height / 1000.0 * 0.8
    ts = cv2.getTextSize(t_str, cv2.FONT_HERSHEY_SIMPLEX, font_scale, 2)[0]
    
    tx = (width - ts[0]) // 2
    ty = y + (footer_h + ts[1]) // 2
    cv2.putText(canvas, t_str, (tx, ty), cv2.FONT_HERSHEY_SIMPLEX, font_scale, COLOR_TEXT_WHITE, 2)

def map_coord(val, scale, offset):
    return int(val * scale + offset)

def draw_rounded_rect(img, pt1, pt2, color, thickness=1, radius=10):
    x1, y1 = pt1
    x2, y2 = pt2
    cv2.line(img, (x1 + radius, y1), (x2 - radius, y1), color, thickness)
    cv2.line(img, (x2, y1 + radius), (x2, y2 - radius), color, thickness)
    cv2.line(img, (x1 + radius, y2), (x2 - radius, y2), color, thickness)
    cv2.line(img, (x1, y1 + radius), (x1, y2 - radius), color, thickness)
    cv2.ellipse(img, (x1 + radius, y1 + radius), (radius, radius), 180, 0, 90, color, thickness)
    cv2.ellipse(img, (x2 - radius, y1 + radius), (radius, radius), 270, 0, 90, color, thickness)
    cv2.ellipse(img, (x2 - radius, y2 - radius), (radius, radius), 0, 0, 90, color, thickness)
    cv2.ellipse(img, (x1 + radius, y2 - radius), (radius, radius), 90, 0, 90, color, thickness)

def draw_filled_rounded_rect(img, pt1, pt2, color, radius=10):
    x1, y1 = pt1
    x2, y2 = pt2
    cv2.rectangle(img, (x1+radius, y1), (x2-radius, y2), color, -1)
    cv2.rectangle(img, (x1, y1+radius), (x1+radius, y2-radius), color, -1)
    cv2.rectangle(img, (x2-radius, y1+radius), (x2, y2-radius), color, -1)
    cv2.circle(img, (x1+radius, y1+radius), radius, color, -1)
    cv2.circle(img, (x2-radius, y1+radius), radius, color, -1)
    cv2.circle(img, (x2-radius, y2-radius), radius, color, -1)
    cv2.circle(img, (x1+radius, y2-radius), radius, color, -1)

# ============================================================================
# DIALOG CLASSES
# ============================================================================
class AdminPromptDialog:
    def __init__(self):
        # Colors
        self.COLOR_BG = "#FFFFFF"
        self.COLOR_PRIMARY = "#1b4d3e" # Dark Green
        self.COLOR_TEXT = "#333333"
        self.COLOR_WHITE = "#FFFFFF"
        
        # Setup Window
        self.root = tk.Tk()
        self.root.title("Authentication Notice")
        self.root.configure(bg=self.COLOR_BG)
        self.root.attributes('-fullscreen', True)
        
        # Get screen dims
        sw = self.root.winfo_screenwidth()
        sh = self.root.winfo_screenheight()
        
        # --- HEADER ---
        header_frame = tk.Frame(self.root, bg=self.COLOR_PRIMARY, height=int(sh*0.12))
        header_frame.pack(side="top", fill="x")
        header_frame.pack_propagate(False) # Force height
        
        # Logo & Title Container
        title_container = tk.Frame(header_frame, bg=self.COLOR_PRIMARY)
        title_container.place(relx=0.5, rely=0.5, anchor="center")
        
        # Logo (Try load)
        logo_path = os.path.join(script_dir, "bpc-logo.png")
        if os.path.exists(logo_path):
            try:
                pil_img = Image.open(logo_path)
                # Resize to ~80px height
                h = int(sh*0.12 * 0.8)
                w = int(pil_img.width * (h / pil_img.height))
                pil_img = pil_img.resize((w, h), Image.Resampling.LANCZOS)
                self.logo_photo = ImageTk.PhotoImage(pil_img)
                tk.Label(title_container, image=self.logo_photo, bg=self.COLOR_PRIMARY).pack(side="left", padx=10)
            except: pass
            
        tk.Label(title_container, text="Automated Face Recognition Attendance System", 
                fg="white", bg=self.COLOR_PRIMARY, font=("Segoe UI", 24, "bold")).pack(side="left", padx=10)
        
        # --- FOOTER ---
        footer_frame = tk.Frame(self.root, bg=self.COLOR_PRIMARY, height=int(sh*0.08))
        footer_frame.pack(side="bottom", fill="x")
        footer_frame.pack_propagate(False)
        
        self.time_label = tk.Label(footer_frame, text="", fg="white", bg=self.COLOR_PRIMARY, font=("Segoe UI", 16))
        self.time_label.place(relx=0.5, rely=0.5, anchor="center")
        self.update_clock()
        
        # --- MAIN CONTENT ---
        content_frame = tk.Frame(self.root, bg=self.COLOR_BG)
        content_frame.pack(side="top", expand=True, fill="both")
        
        # Center Box
        msg_frame = tk.Frame(content_frame, bg=self.COLOR_BG)
        msg_frame.place(relx=0.5, rely=0.5, anchor="center")
        
        tk.Label(msg_frame, text="Face Not Recognize", fg="#dc3545", bg=self.COLOR_BG, 
                font=("Segoe UI", 36, "bold")).pack(pady=(0, 20))
                
        tk.Label(msg_frame, text="Please go to the Admin for manual attendance.", fg=self.COLOR_TEXT, bg=self.COLOR_BG, 
                font=("Segoe UI", 20)).pack(pady=(0, 40))
        
        tk.Button(msg_frame, text="Return to Camera", bg="#198754", fg="white", 
                 font=("Segoe UI", 16, "bold"), relief="flat", cursor="hand2", 
                 command=self.root.destroy, padx=30, pady=10).pack(pady=20)

        # Auto-close after 5 seconds
        self.root.after(5000, self.root.destroy)
        
        # Hotkeys
        self.root.bind('<Return>', lambda e: self.root.destroy())
        self.root.bind('<Escape>', lambda e: self.root.destroy())
        
        self.root.lift()
        self.root.attributes('-topmost', True)
        self.root.after_idle(self.root.attributes, '-topmost', False)
        self.root.mainloop()

    def update_clock(self):
        try:
            now = datetime.now().strftime("%B %d, %Y %A | %I:%M:%S %p")
            self.time_label.config(text=now)
            self.root.after(1000, self.update_clock)
        except: pass

# ============================================================================
# MAIN APPLICATION LOGIC
# ============================================================================
def run_app():
    logger = AttendanceLogger()
    print("Loading Models...")
    
    script_dir = os.path.dirname(os.path.abspath(__file__))
    models_dir = os.path.join(script_dir, "models")
    
    # YuNet
    yunet_path = os.path.join(models_dir, "openCV_YuNet", "face_detection_yunet_2023mar.onnx")
    detector_yunet = None
    if os.path.exists(yunet_path):
        detector_yunet = cv2.FaceDetectorYN.create(
            model=yunet_path, config="", input_size=(320, 320),
            score_threshold=0.8, nms_threshold=0.3, top_k=5000
        )
    
    # Liveness Detector
    liveness_detector = None
    minifasnet_path = os.path.join(models_dir, "miniFastnet", "MiniFASNetV2_TEST.onnx")
    if not os.path.exists(minifasnet_path):
        # Fallback to standard name
        minifasnet_path = os.path.join(models_dir, "miniFastnet", "MiniFASNetV2.onnx")
        
    if os.path.exists(minifasnet_path):
        print(f"Initializing Liveness Detector ({os.path.basename(minifasnet_path)})...")
        liveness_detector = LivenessDetector(minifasnet_path, threshold=LIVENESS_THRESHOLD)
    else:
        print("⚠️ Liveness model not found (waiting for download)")
    
    try:
        # Use AuraFace if available
        # root=script_dir because InsightFace appends 'models' automatically
        # resulting in script_dir/models/auraface
        face_app = FaceAnalysis(name='auraface', root=script_dir, providers=['CPUExecutionProvider'])
        face_app.prepare(ctx_id=0, det_size=(640, 640))
    except:
        print("Fallback to buffalo_l")
        face_app = FaceAnalysis(name='buffalo_l', root=script_dir, providers=['CPUExecutionProvider'])
        face_app.prepare(ctx_id=0, det_size=(640, 640))
        
    all_embeddings = None
    employee_info = []
    if os.path.exists(AUTHORIZED_EMBEDDINGS_PATH):
        try:
            data = np.load(AUTHORIZED_EMBEDDINGS_PATH, allow_pickle=True).item()
            all_embeddings = data.get('embeddings')
            employee_info = data.get('employee_info', [])
        except Exception as e:
            print(f"Error loading embeddings: {e}")
            all_embeddings = None
    
    # Check if database is actually populated
    db_populated = all_embeddings is not None and len(all_embeddings) > 0
    if not db_populated:
        print("⚠️ WARNING: No face embeddings loaded. Verification will be disabled.")
        
    profile_pics = load_profile_pictures(employee_info, os.path.join(script_dir, "database", "user_profile"))
    logo_path = os.path.join(script_dir, "bpc-logo.png")
    logo_img = cv2.imread(logo_path, cv2.IMREAD_UNCHANGED) if os.path.exists(logo_path) else None
    
    cap = cv2.VideoCapture(0)
    window_name = "Face Attendance"
    cv2.namedWindow(window_name, cv2.WINDOW_NORMAL)
    cv2.setWindowProperty(window_name, cv2.WND_PROP_FULLSCREEN, cv2.WINDOW_FULLSCREEN)
    
    is_fullscreen = True
    consecutive_failures = 0
    last_simulation = 0
    
    frontal_start = None
    is_frontal_stable = False
    verification_done = False
    status_text = ""
    status_color = (255, 255, 255)
    last_verify_time = None
    matched_emp = None
    
    last_face_coords = None 
    
    while True:
        ret, frame = cap.read()
        if not ret: break
        
        if keyboard.is_pressed('alt+shift+delete'):
            if time.time() - last_simulation > 1.0:
                consecutive_failures += 1
                last_simulation = time.time()
                print(f"Simulated Failure: {consecutive_failures}")

        if consecutive_failures >= 3:
            print("Triggering Admin Prompt...")
            # Release camera temporarily if needed, or just destroy windows
            # cv2.destroyAllWindows() # Optional: Might cause flicker or loss of context, but cleaner for dialog
            
            # Show the dialog
            dlg = AdminPromptDialog()
            
            # After dialog closes, reset failures and continue
            consecutive_failures = 0
            frontal_start = None # Reset tracking
            
            # Ensure window is back
            cv2.namedWindow(window_name, cv2.WINDOW_NORMAL)
            if is_fullscreen: cv2.setWindowProperty(window_name, cv2.WND_PROP_FULLSCREEN, cv2.WINDOW_FULLSCREEN)

        try:
             rect = cv2.getWindowImageRect(window_name)
             sw, sh = (rect[2], rect[3]) if rect[2] > 0 else (1920, 1080)
        except: sw, sh = 1920, 1080
        
        canvas = np.zeros((sh, sw, 3), dtype=np.uint8)
        draw_kiosk_header(canvas, sw, sh, logo_img)
        draw_kiosk_footer(canvas, sw, sh)
        
        hh = int(sh * HEADER_HEIGHT_RATIO)
        fh = int(sh * FOOTER_HEIGHT_RATIO)
        ch = sh - hh - fh
        cy = hh
        
        fh_v, fw_v = frame.shape[:2]
        scale = min(sw/fw_v, ch/fh_v)
        nw, nh = int(fw_v*scale), int(fh_v*scale)
        resized_frame = cv2.resize(frame, (nw, nh))
        
        off_x = (sw - nw) // 2
        off_y = cy + (ch - nh) // 2
        
        canvas[off_y:off_y+nh, off_x:off_x+nw] = resized_frame
        
        faces = None
        if detector_yunet:
             detector_yunet.setInputSize((fw_v, fh_v))
             _, faces = detector_yunet.detect(frame)
        
        if faces is not None and len(faces) == 1:
             face = faces[0]
             bx, by, bw, bh = face[0:4].astype(int)
             kps = face[4:14].reshape((5, 2)).astype(int)
             conf = face[14]
             
             mcx = map_coord(bx, scale, off_x)
             mcy = map_coord(by, scale, off_y)
             mcw = int(bw * scale)
             mch = int(bh * scale)
             
             last_face_coords = (mcx, mcy, mcw, mch)
             
             # --- Strict Gates ---
             is_frontal = is_frontal_face(kps, fw_v, fh_v)
             is_close, dist_status = is_face_close_enough(face[0:4], fw_v, fh_v)
             
             # --- Liveness Check ---
             is_real = True
             liveness_score = 0.0
             if liveness_detector and ENABLE_LIVENESS_CHECK:
                  liveness_score, is_real = liveness_detector.check(frame, face[0:4].astype(int))
                  # Visual debug: Show score (Commented out for production)
                  # score_color = (0, 255, 0) if is_real else (0, 0, 255)
                  # cv2.putText(canvas, f"Live: {liveness_score:.2f}", (mcx, mcy-35), cv2.FONT_HERSHEY_SIMPLEX, 0.6, score_color, 2)

             # Visual Feedback: Green if ready, Red if not
             # READY CONDITION: Database has people + Frontal + Close + High Confidence + Real
             is_ready = db_populated and is_frontal and is_close and (conf > 0.8) and is_real
             
             col = (0, 255, 0) if is_ready else (0, 0, 255)
             cv2.rectangle(canvas, (mcx, mcy), (mcx+mcw, mcy+mch), col, 2)

             # Guidance Text
             guidance_text = ""
             if not db_populated:
                 guidance_text = "No Registered Faces"
             elif not is_real:
                 guidance_text = "FAKE FACE DETECTED"
             elif not is_frontal:
                 guidance_text = "Look at Camera"
             elif dist_status == "too_far":
                 guidance_text = "Please Stand Closer"
             elif dist_status == "too_close":
                 guidance_text = "Move Back"
             
             if guidance_text:
                 # Calculate text size to center it or place it well
                 g_scale = 1.0
                 g_thick = 2
                 g_size = cv2.getTextSize(guidance_text, cv2.FONT_HERSHEY_SIMPLEX, g_scale, g_thick)[0]
                 g_x = mcx + (mcw - g_size[0]) // 2
                 g_y = mcy - 10
                 if g_y < 30: g_y = mcy + mch + 30 # Move below if too close to top
                 
                 # Draw text with outline/background for visibility
                 cv2.putText(canvas, guidance_text, (g_x, g_y), cv2.FONT_HERSHEY_SIMPLEX, g_scale, (0,0,0), g_thick+2)
                 g_color = (0, 255, 255) # Yellow default
                 if not is_real: g_color = (0, 0, 255) # Red for fake
                 cv2.putText(canvas, guidance_text, (g_x, g_y), cv2.FONT_HERSHEY_SIMPLEX, g_scale, g_color, g_thick)
             
             if is_ready:
                 is_cd = False
                 if last_verify_time and (time.time() - last_verify_time < 3.0): is_cd = True
                 
                 if not is_cd:
                     if not frontal_start: frontal_start = time.time()
                     
                     if time.time() - frontal_start > 1.0:
                         objs = face_app.get(frame)
                         if objs:
                             objs = sorted(objs, key=lambda x: (x.bbox[2]-x.bbox[0]) * (x.bbox[3]-x.bbox[1]), reverse=True)
                             emb = objs[0].normed_embedding
                             
                             if all_embeddings is not None:
                                 sims = np.dot(all_embeddings, emb)
                                 idx = np.argmax(sims)
                                 max_sim = sims[idx]
                                 
                                 if max_sim > 0.6:
                                      matched_emp = employee_info[idx]
                                      
                                      eid = matched_emp['db_id']
                                      has_schedule = check_employee_schedule(eid)
                                      lt = 'visit' if not has_schedule else None
                                      
                                      # Determine cooldown
                                      cooldown = LOGIN_COOLDOWN_MINUTES if ENABLE_LOGIN_COOLDOWN else 0
                                      if lt == 'visit':
                                          cooldown = 0
                                      
                                      min_work = MIN_WORK_DURATION_MINUTES
                                      single_sess = ENABLE_SINGLE_SESSION
                                      
                                      res = logger.log_attendance(eid, log_type=lt, source='webcam', 
                                                                cooldown_minutes=cooldown,
                                                                min_work_minutes=min_work,
                                                                one_session_per_day=single_sess)
                                      
                                      if res['success']:
                                           rt = res['log_type']
                                           if rt == 'time_in': status_text = "Verified - Time In"; status_color = (0, 255, 0)
                                           elif rt == 'time_out': status_text = "Verified - Time Out"; status_color = (0, 140, 255)
                                           elif rt == 'visit': status_text = "Verified - Visit"; status_color = (255, 0, 255)
                                           else: status_text = rt; status_color = (255, 255, 255)
                                      elif res.get('status') == 'cooldown':
                                           # Cooldown Active - Show Info
                                           status_text = res.get('message', "Already Verified")
                                           status_color = (0, 215, 255) # Gold/Orangey
                                      elif res.get('status') == 'too_early':
                                           # Tried to Time Out too soon
                                           status_text = res.get('message', "Too Early to Time Out")
                                           status_color = (255, 165, 0) # Orange
                                      elif res.get('status') == 'completed':
                                           # Already finished for the day
                                           status_text = "Attendance Completed"
                                           status_color = (0, 100, 255) # Blue
                                      else:
                                           status_text = "Included / Error"; status_color = (0, 0, 255)
                                           
                                      # Dynamically load the latest profile picture from disk for this session
                                      matched_emp['dynamic_pic'] = None
                                      emp_code = matched_emp.get('employee_code')
                                      if emp_code:
                                          user_profile_dir = os.path.join(script_dir, "database", "user_profile")
                                          for ext in ['.jpg', '.png', '.jpeg']:
                                              p = os.path.join(user_profile_dir, f"{emp_code}{ext}")
                                              if os.path.exists(p):
                                                  matched_emp['dynamic_pic'] = cv2.imread(p)
                                                  break
                                                  
                                      verification_done = True
                                      last_verify_time = time.time()
                                      consecutive_failures = 0
                                 else:
                                      # Not recognized
                                      consecutive_failures += 1
                                      frontal_start = None 
                                      
                                      # Visual feedback for Unknown Face
                                      # We overwrite the box color to Red and show text
                                      col = (0, 0, 255) # Red
                                      cv2.rectangle(canvas, (mcx, mcy), (mcx+mcw, mcy+mch), col, 2)
                                      
                                      guidance_text = "Unknown Face"
                                      # Calculate text size to center it
                                      g_scale = 1.0
                                      g_thick = 2
                                      g_size = cv2.getTextSize(guidance_text, cv2.FONT_HERSHEY_SIMPLEX, g_scale, g_thick)[0]
                                      g_x = mcx + (mcw - g_size[0]) // 2
                                      g_y = mcy - 10
                                      if g_y < 30: g_y = mcy + mch + 30 
                                      
                                      # Draw text
                                      cv2.putText(canvas, guidance_text, (g_x, g_y), cv2.FONT_HERSHEY_SIMPLEX, g_scale, (0,0,0), g_thick+2)
                                      cv2.putText(canvas, guidance_text, (g_x, g_y), cv2.FONT_HERSHEY_SIMPLEX, g_scale, (0, 0, 255), g_thick)
                         else:
                              frontal_start = None
                 else:
                      frontal_start = None
             else:
                  frontal_start = None

        if verification_done and matched_emp:
             if time.time() - last_verify_time > 3.0:
                 verification_done = False
                 matched_emp = None
             else:
                 card_w, card_h = 420, 120
                 
                 if last_face_coords:
                     cx, cy, cw, ch = last_face_coords
                     card_x = cx + (cw - card_w) // 2
                     card_y = cy + ch + 30
                 else:
                     card_x = (sw - card_w) // 2
                     card_y = sh - int(sh * FOOTER_HEIGHT_RATIO) - card_h - 20
                     
                 card_x = max(10, min(card_x, sw - card_w - 10))
                 if card_y + card_h > (sh - int(sh * FOOTER_HEIGHT_RATIO)):
                      card_y = (sh - int(sh * FOOTER_HEIGHT_RATIO)) - card_h - 10
                 
                 # shadow_off = 5
                 # draw_filled_rounded_rect(canvas, (card_x+shadow_off, card_y+shadow_off), 
                 #                        (card_x+card_w+shadow_off, card_y+card_h+shadow_off), 
                 #                        (50, 50, 50))
                                        
                 draw_filled_rounded_rect(canvas, (card_x, card_y), (card_x+card_w, card_y+card_h), (255, 255, 255))
                 # draw_rounded_rect(canvas, (card_x, card_y), (card_x+card_w, card_y+card_h), status_color, thickness=2)
                 
                 pic_size = 90
                 pic_x = card_x + 20
                 pic_y = card_y + (card_h - pic_size) // 2
                 
                 code = matched_emp.get('employee_code')
                 pic = matched_emp.get('dynamic_pic')
                 if pic is None:
                      pic = profile_pics.get(code)
                 
                 if pic is not None:
                      try:
                          p_res = cv2.resize(pic, (pic_size, pic_size))
                          mask = np.zeros((pic_size, pic_size), dtype=np.uint8)
                          cv2.circle(mask, (pic_size//2, pic_size//2), pic_size//2, 255, -1)
                          
                          roi = canvas[pic_y:pic_y+pic_size, pic_x:pic_x+pic_size]
                          roi[mask==255] = p_res[mask==255]
                      except:
                          cv2.circle(canvas, (pic_x+pic_size//2, pic_y+pic_size//2), pic_size//2, (200,200,200), -1)
                 else:
                      cv2.circle(canvas, (pic_x+pic_size//2, pic_y+pic_size//2), pic_size//2, (200,200,200), -1)
                 
                 text_x = pic_x + pic_size + 20
                 cv2.putText(canvas, matched_emp.get('full_name', matched_emp.get('name', 'Unknown')), (text_x, card_y + 50), 
                            cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0,0,0), 2)
                 cv2.putText(canvas, code, (text_x, card_y + 75), 
                            cv2.FONT_HERSHEY_SIMPLEX, 0.6, (50,50,50), 1)
                 cv2.putText(canvas, status_text, (text_x, card_y + 105), 
                            cv2.FONT_HERSHEY_SIMPLEX, 0.5, status_color, 1)

        cv2.imshow(window_name, canvas)
        if cv2.waitKey(1) == 27: 
             break
             
    cap.release()
    cv2.destroyAllWindows()

if __name__ == "__main__":
    run_app()
