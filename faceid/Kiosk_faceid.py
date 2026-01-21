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
try:
    from attendance_logger import AttendanceLogger
    from database.init_local_db import init_local_db
except ImportError:
    pass 

# Constants
AUTHORIZED_EMBEDDINGS_PATH = os.path.join(script_dir, "database", "authorized_embeddings.npy")
ATTENDANCE_LOGGING_ENABLED = True
ENABLE_LOGOUT_RESTRICTION = True
ENABLE_LOGIN_COOLDOWN = True
LOGIN_COOLDOWN_MINUTES = 5

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
    
    min_ratio = 0.32 
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
class ManualLoginDialog:
    def __init__(self, attendance_logger):
        self.result = False
        self.attendance_logger = attendance_logger
        self.employee_data = None
        
        self.root = tk.Tk()
        self.root.title("Manual Verification")
        
        w, h = 500, 400
        sw = self.root.winfo_screenwidth()
        sh = self.root.winfo_screenheight()
        x = (sw - w) // 2
        y = (sh - h) // 2
        self.root.geometry(f"{w}x{h}+{x}+{y}")
        self.root.configure(bg='white')
        
        tk.Label(self.root, text="Too many attempts please log in manually", fg="#dc3545", bg="white", font=("Segoe UI", 12)).pack(pady=20)
        
        tk.Label(self.root, text="ID Number", bg="white", font=("Segoe UI", 10, "bold")).pack(fill="x", padx=40)
        self.entry_id = tk.Entry(self.root, font=("Segoe UI", 12))
        self.entry_id.pack(fill="x", padx=40, pady=(0, 15))
        
        tk.Label(self.root, text="Password", bg="white", font=("Segoe UI", 10, "bold")).pack(fill="x", padx=40)
        self.entry_pass = tk.Entry(self.root, font=("Segoe UI", 12), show="*")
        self.entry_pass.pack(fill="x", padx=40, pady=(0, 20))
        
        self.btn = tk.Button(self.root, text="Log In", bg="#198754", fg="white", font=("Segoe UI", 12, "bold"), command=self.verify)
        self.btn.pack(fill="x", padx=40, ipady=5)
        
        self.root.bind('<Return>', lambda e: self.verify())
        self.entry_id.focus()
        
        self.root.lift()
        self.root.attributes('-topmost',True)
        self.root.after_idle(self.root.attributes,'-topmost',False)
        self.root.mainloop()

    def verify(self):
        emp_id = self.entry_id.get().strip()
        pw = self.entry_pass.get()
        
        if not emp_id or not pw:
            messagebox.showerror("Error", "Enter all fields")
            return
            
        try:
            url = "http://localhost/EndDev/login/auth.php?action=login"
            data = urllib.parse.urlencode({'employee_id': emp_id, 'password': pw}).encode('utf-8')
            req = urllib.request.Request(url, data=data)
            with urllib.request.urlopen(req) as resp:
                res = json.loads(resp.read().decode('utf-8'))
                
            if res.get('success'):
                user = res.get('user', {})
                code = user.get('employee_id')
                local = self.attendance_logger.get_employee_by_code(code)
                if local:
                    self.employee_data = local
                    self.result = True
                    self.root.destroy()
                else:
                    messagebox.showerror("Error", "Employee not found locally.")
            else:
                messagebox.showerror("Error", "Invalid credentials")
        except Exception as e:
            messagebox.showerror("Error", f"Connection error: {e}")

# ============================================================================
# MAIN APPLICATION LOGIC
# ============================================================================
def run_app():
    logger = AttendanceLogger()
    print("Loading Models...")
    
    script_dir = os.path.dirname(os.path.abspath(__file__))
    yunet_path = os.path.join(script_dir, "face_detection_yunet_2023mar.onnx")
    detector_yunet = None
    if os.path.exists(yunet_path):
        detector_yunet = cv2.FaceDetectorYN.create(
            model=yunet_path, config="", input_size=(320, 320),
            score_threshold=0.8, nms_threshold=0.3, top_k=5000
        )
    
    try:
        face_app = FaceAnalysis(name='auraface', root=script_dir, providers=['CPUExecutionProvider'])
        face_app.prepare(ctx_id=0, det_size=(640, 640))
    except:
        print("Fallback to buffalo_l")
        face_app = FaceAnalysis(name='buffalo_l', providers=['CPUExecutionProvider'])
        face_app.prepare(ctx_id=0, det_size=(640, 640))
        
    all_embeddings = None
    employee_info = []
    if os.path.exists(AUTHORIZED_EMBEDDINGS_PATH):
        data = np.load(AUTHORIZED_EMBEDDINGS_PATH, allow_pickle=True).item()
        all_embeddings = data['embeddings']
        employee_info = data['employee_info']
        
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

        manual_login_user = None
        if consecutive_failures >= 3:
            print("Triggering Manual Login...")
            cv2.destroyAllWindows() 
            dlg = ManualLoginDialog(logger)
            
            if dlg.result:
                manual_login_user = dlg.employee_data
                consecutive_failures = 0
                print(f"Manual Login Success: {manual_login_user.get('full_name')}")
                
                matched_emp = manual_login_user
                
                emp_id = matched_emp['db_id']
                has_sched = check_employee_schedule(emp_id)
                l_type = 'visit' if not has_sched else None
                res = logger.log_attendance(emp_id, log_type=l_type, source='manual login')
                
                if res['success']:
                    verification_done = True
                    last_verify_time = time.time()
                    
                    rt = res['log_type']
                    if rt == 'time_in': status_text = "Verified - Time In"; status_color = (0, 255, 0)
                    elif rt == 'time_out': status_text = "Verified - Time Out"; status_color = (0, 140, 255)
                    elif rt == 'visit': status_text = "Verified - Visit"; status_color = (255, 0, 255)
                    else: status_text = rt; status_color = (255, 255, 255)
                    
                    print(f"Logged: {status_text}")
                else:
                    status_text = "Error Logging"
                    status_color = (0, 0, 255)
                    verification_done = True
                    last_verify_time = time.time()
            else:
                consecutive_failures = 0 
            
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
             is_close, _ = is_face_close_enough(face[0:4], fw_v, fh_v)
             
             # Visual Feedback: Green if ready, Red if not
             # READY CONDITION: Frontal + Close + High Confidence
             is_ready = is_frontal and is_close and (conf > 0.8)
             
             col = (0, 255, 0) if is_ready else (0, 0, 255)
             cv2.rectangle(canvas, (mcx, mcy), (mcx+mcw, mcy+mch), col, 2)
             
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
                                      
                                      res = logger.log_attendance(eid, log_type=lt, source='webcam')
                                      
                                      if res['success']:
                                           rt = res['log_type']
                                           if rt == 'time_in': status_text = "Verified - Time In"; status_color = (0, 255, 0)
                                           elif rt == 'time_out': status_text = "Verified - Time Out"; status_color = (0, 140, 255)
                                           elif rt == 'visit': status_text = "Verified - Visit"; status_color = (255, 0, 255)
                                           else: status_text = rt; status_color = (255, 255, 255)
                                      else:
                                           status_text = "Included / Error"; status_color = (0, 0, 255)
                                           
                                      verification_done = True
                                      last_verify_time = time.time()
                                      consecutive_failures = 0
                                 else:
                                      consecutive_failures += 1
                                      frontal_start = None 
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
