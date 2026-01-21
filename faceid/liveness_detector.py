import cv2
import numpy as np
import os

class LivenessDetector:
    """
    Liveness detection using MiniFASNetV2 (ONNX).
    Checks if a face is real or spoof (photo/screen).
    Uses onnxruntime for better compatibility (e.g. with quantized models).
    """
    def __init__(self, model_path, threshold=0.7):
        self.threshold = threshold
        self.model_path = model_path
        self.ort_session = None
        self.input_name = None
        self._load_model()
        
    def _load_model(self):
        if not os.path.exists(self.model_path):
            print(f"[Liveness] Model not found at {self.model_path}")
            return
            
        try:
            import onnxruntime as ort
            # Use CUDA if available, else CPU
            providers = ['CUDAExecutionProvider', 'CPUExecutionProvider']
            self.ort_session = ort.InferenceSession(self.model_path, providers=providers)
            self.input_name = self.ort_session.get_inputs()[0].name
            print(f"[Liveness] Model loaded from {self.model_path} using onnxruntime")
        except Exception as e:
            print(f"[Liveness] Error loading model with onnxruntime: {e}")
            # Fallback to OpenCV DNN if ORT fails? (Unlikely if installed)
            try:
                print("[Liveness] Attempting fallback to OpenCV DNN...")
                self.net = cv2.dnn.readNetFromONNX(self.model_path)
                print(f"[Liveness] Model loaded (OpenCV fallback)")
            except Exception as e2:
                print(f"[Liveness] OpenCV fallback failed: {e2}")

    def check(self, frame, face_box):
        """
        Check liveness of the face in the box.
        """
        if self.ort_session is None and getattr(self, 'net', None) is None:
            return 0.0, False
             
        x, y, w, h = face_box
        
        # 1. Padding (Model expects context)
        scale = 2.7
        cx = x + w // 2
        cy = y + h // 2
        nw = int(w * scale)
        nh = int(h * scale)
        nx = max(0, cx - nw // 2)
        ny = max(0, cy - nh // 2)
        h_frame, w_frame = frame.shape[:2]
        nx2 = min(w_frame, nx + nw)
        ny2 = min(h_frame, ny + nh)
        
        if nx2 - nx < 20 or ny2 - ny < 20: return 0.0, False
             
        crop = frame[ny:ny2, nx:nx2]
        if crop.size == 0: return 0.0, False

        # 2. Resize to 128x128 (Common for MiniFASNet variants like Silent-Face-Anti-Spoofing)
        # Note: Original MiniFASNetV2 was 80x80, but many implementations/exports use 128x128.
        target_size = 128
        try:
            blob = cv2.resize(crop, (target_size, target_size))
        except:
            return 0.0, False
            
        # 3. Preprocess
        # A. Colorspace: Model likely expects RGB (OpenCV is BGR)
        blob = cv2.cvtColor(blob, cv2.COLOR_BGR2RGB)
        
        # B. Normalization: [0, 255] -> [0.0, 1.0]
        # This prevents large logits that cause NaN in softmax
        blob = blob.astype(np.float32) / 255.0
        
        blob = np.transpose(blob, (2, 0, 1)) # HWC -> CHW
        blob = np.expand_dims(blob, 0) # Add batch dim
        
        # 4. Inference
        try:
            probs = None
            raw_preds = None
            
            if self.ort_session:
                inputs = {self.input_name: blob}
                raw_preds = self.ort_session.run(None, inputs)[0]
            elif getattr(self, 'net', None):
                self.net.setInput(blob)
                raw_preds = self.net.forward()
            
            if raw_preds is not None:
                # Stable Softmax: subtract max to prevent overflow
                shift_preds = raw_preds - np.max(raw_preds, axis=1, keepdims=True)
                exps = np.exp(shift_preds)
                probs = exps / np.sum(exps, axis=1, keepdims=True)
                
                # Debug info (optional)
                # print(f"Raw: {raw_preds} -> Probs: {probs}")
            
            if probs is not None:
                # Based on user feedback:
                # Index 0 appears to be Real
                # Index 1 appears to be Spoof
                # (Standard MiniFASNet is usually [Spoof, Real], but this one seems inverted or user is seeing inv results)
                
                real_score = float(probs[0][0]) 
                spoof_score = float(probs[0][1])
                
                # Debug output to console to confirm mapping
                # print(f"[Liveness] Real (0): {real_score:.4f}, Spoof (1): {spoof_score:.4f}")
                
                if np.isnan(real_score): real_score = 0.0
                
                return real_score, real_score > self.threshold
                
        except Exception as e:
            print(f"[Liveness] Inference error: {e}")
                
        except Exception as e:
            print(f"[Liveness] Inference error: {e}")
            
        return 0.0, False
