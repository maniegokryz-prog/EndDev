/**
 * Face Detection Module
 * Handles AI-powered face detection using FaceAPI.js
 */

class FaceDetection {
    constructor() {
        this.faceApiLoaded = false;
        this.detectionCanvas = null;
        this.detectionCtx = null;
    }

    async initialize() {
        await this.loadFaceApiModels();
        this.setupDetectionCanvas();
    }

    async loadFaceApiModels() {
        try {
            console.log('Loading FaceAPI models...');
            
            // Try local models first, then fallback to CDN
            const modelSources = [
                './assets/models/',  // Local models (preferred)
                'assets/models/',    // Alternative local path
                'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/weights/',  // CDN fallback
                'https://unpkg.com/face-api.js@0.22.2/weights/',
                'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights/'
            ];
            
            let modelsLoaded = false;
            let lastError = null;
            
            for (let i = 0; i < modelSources.length && !modelsLoaded; i++) {
                try {
                    const isLocal = i < 2;
                    const sourceType = isLocal ? 'local' : 'CDN';
                    
                    console.log(`Trying ${sourceType} model source ${i + 1}:`, modelSources[i]);
                    
                    await Promise.all([
                        faceapi.nets.tinyFaceDetector.loadFromUri(modelSources[i]),
                        faceapi.nets.faceLandmark68Net.loadFromUri(modelSources[i])
                    ]);
                    
                    modelsLoaded = true;
                    console.log(`✓ FaceAPI models loaded successfully from ${sourceType} source:`, modelSources[i]);
                    
                } catch (error) {
                    console.warn(`Failed to load from source ${i + 1} (${modelSources[i]}):`, error.message);
                    lastError = error;
                    
                    if (i < modelSources.length - 1) {
                        console.log('Trying next source...');
                        await new Promise(resolve => setTimeout(resolve, 500));
                    }
                }
            }
            
            if (modelsLoaded) {
                this.faceApiLoaded = true;
            } else {
                throw lastError || new Error('All model sources failed');
            }
            
        } catch (error) {
            console.error('Failed to load FaceAPI models from all sources:', error);
            this.faceApiLoaded = false;
            throw error;
        }
    }

    setupDetectionCanvas() {
        this.detectionCanvas = document.getElementById('detection-overlay');
        this.detectionCtx = this.detectionCanvas.getContext('2d');
        this.syncCanvasSize();
    }

    syncCanvasSize() {
        const video = document.getElementById('video');
        if (video && this.detectionCanvas) {
            // Wait for video to load to get its actual display size
            const resizeCanvas = () => {
                const rect = video.getBoundingClientRect();
                this.detectionCanvas.width = rect.width;
                this.detectionCanvas.height = rect.height;
                this.detectionCanvas.style.width = rect.width + 'px';
                this.detectionCanvas.style.height = rect.height + 'px';
            };

            // Resize immediately and on video size changes
            resizeCanvas();
            video.addEventListener('loadedmetadata', resizeCanvas);
            video.addEventListener('resize', resizeCanvas);
            window.addEventListener('resize', resizeCanvas);
        }
    }

    async detectFaceWithFaceAPI(video) {
        if (!this.faceApiLoaded || typeof faceapi === 'undefined') {
            return null;
        }

        try {
            const detections = await faceapi
                .detectAllFaces(video, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks();

            if (detections && detections.length > 0) {
                const detection = detections[0];
                return {
                    box: detection.detection.box,
                    landmarks: detection.landmarks,
                    confidence: detection.detection.score
                };
            }
        } catch (error) {
            console.warn('FaceAPI detection failed:', error);
        }

        return null;
    }

    detectFaceBasic(video, canvas) {
        const context = canvas.getContext('2d');
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
        const data = imageData.data;
        
        let skinPixels = 0;
        let totalPixels = data.length / 4;
        
        // Simple skin tone detection
        for (let i = 0; i < data.length; i += 4) {
            const r = data[i];
            const g = data[i + 1];
            const b = data[i + 2];
            
            if (r > 95 && g > 40 && b > 20 && 
                Math.max(r, g, b) - Math.min(r, g, b) > 15 &&
                Math.abs(r - g) > 15 && r > g && r > b) {
                skinPixels++;
            }
        }
        
        const skinPercentage = skinPixels / totalPixels;
        
        if (skinPercentage > 0.02) {
            return {
                x: canvas.width * 0.25,
                y: canvas.height * 0.25,
                width: canvas.width * 0.5,
                height: canvas.height * 0.5,
                confidence: Math.min(skinPercentage * 20, 1)
            };
        }
        
        return null;
    }

    drawFaceDetectionWithLandmarks(detection) {
        if (!this.detectionCanvas || !detection.landmarks) return;
        
        this.detectionCtx.clearRect(0, 0, this.detectionCanvas.width, this.detectionCanvas.height);
        
        const video = document.getElementById('video');
        const scaleX = this.detectionCanvas.width / video.videoWidth;
        const scaleY = this.detectionCanvas.height / video.videoHeight;
        
        // Draw face box
        this.detectionCtx.strokeStyle = '#00FF00';
        this.detectionCtx.lineWidth = 2;
        this.detectionCtx.strokeRect(
            detection.box.x * scaleX,
            detection.box.y * scaleY,
            detection.box.width * scaleX,
            detection.box.height * scaleY
        );
        
        // Draw landmarks - simple red dots like original
        const landmarks = detection.landmarks.positions;
        this.detectionCtx.fillStyle = '#FF0000';
        
        landmarks.forEach(point => {
            this.detectionCtx.fillRect(
                point.x * scaleX - 1,
                point.y * scaleY - 1,
                2, 2
            );
        });
    }

    drawBasicFaceDetection(faceRegion) {
        if (!this.detectionCanvas) return;
        
        this.detectionCtx.clearRect(0, 0, this.detectionCanvas.width, this.detectionCanvas.height);
        
        const video = document.getElementById('video');
        let scaleX = this.detectionCanvas.width / video.videoWidth;
        let scaleY = this.detectionCanvas.height / video.videoHeight;
        
        // Draw face detection rectangle
        this.detectionCtx.strokeStyle = '#00FF00';
        this.detectionCtx.lineWidth = 3;
        this.detectionCtx.setLineDash([]);
        
        let rectX = faceRegion.x * scaleX;
        let rectY = faceRegion.y * scaleY;
        let rectW = faceRegion.width * scaleX;
        let rectH = faceRegion.height * scaleY;
        
        this.detectionCtx.strokeRect(rectX, rectY, rectW, rectH);
        
        // Add corner markers
        this.detectionCtx.strokeStyle = '#FFFF00';
        this.detectionCtx.lineWidth = 2;
        let cornerSize = 20;
        
        // Corner markers code...
        this.drawCornerMarkers(rectX, rectY, rectW, rectH, cornerSize);
    }

    drawCornerMarkers(x, y, w, h, size) {
        // Top-left corner
        this.detectionCtx.beginPath();
        this.detectionCtx.moveTo(x, y + size);
        this.detectionCtx.lineTo(x, y);
        this.detectionCtx.lineTo(x + size, y);
        this.detectionCtx.stroke();
        
        // Top-right corner
        this.detectionCtx.beginPath();
        this.detectionCtx.moveTo(x + w - size, y);
        this.detectionCtx.lineTo(x + w, y);
        this.detectionCtx.lineTo(x + w, y + size);
        this.detectionCtx.stroke();
        
        // Bottom-left corner
        this.detectionCtx.beginPath();
        this.detectionCtx.moveTo(x, y + h - size);
        this.detectionCtx.lineTo(x, y + h);
        this.detectionCtx.lineTo(x + size, y + h);
        this.detectionCtx.stroke();
        
        // Bottom-right corner
        this.detectionCtx.beginPath();
        this.detectionCtx.moveTo(x + w - size, y + h);
        this.detectionCtx.lineTo(x + w, y + h);
        this.detectionCtx.lineTo(x + w, y + h - size);
        this.detectionCtx.stroke();
    }

    analyzeFaceOrientation(landmarks) {
        if (!landmarks || !landmarks.positions) {
            return { angle: 'unknown', confidence: 0, lighting: 'unknown' };
        }

        // Get key facial landmarks using FaceAPI.js methods
        const nose = landmarks.getNose();
        const leftEye = landmarks.getLeftEye();
        const rightEye = landmarks.getRightEye();
        const mouth = landmarks.getMouth();
        
        // Calculate nose tip and bridge positions
        const noseTip = nose[3]; // Bottom of nose
        const noseBridge = nose[0]; // Top of nose bridge
        
        // Calculate eye centers
        const leftEyeCenter = this.getAveragePoint(leftEye);
        const rightEyeCenter = this.getAveragePoint(rightEye);
        const mouthCenter = this.getAveragePoint(mouth);
        
        // Calculate horizontal angle (left/right)
        const eyeDistance = rightEyeCenter.x - leftEyeCenter.x;
        const noseMidpoint = (leftEyeCenter.x + rightEyeCenter.x) / 2;
        const noseOffset = noseTip.x - noseMidpoint;
        const horizontalRatio = noseOffset / eyeDistance;
        
        // Calculate vertical angle (up/down)
        const eyeY = (leftEyeCenter.y + rightEyeCenter.y) / 2;
        const noseY = noseTip.y;
        const mouthY = mouthCenter.y;
        
        const faceHeight = mouthY - eyeY;
        const noseVerticalOffset = (noseY - eyeY) / faceHeight;
        
        // Determine orientation
        let angle = 'front';
        let confidence = 0.8;
        
        if (Math.abs(horizontalRatio) > 0.15) {
            angle = horizontalRatio > 0 ? 'right' : 'left';
            confidence = Math.min(0.9, Math.abs(horizontalRatio) * 3);
        } else if (noseVerticalOffset < 0.4) {
            angle = 'up';
            confidence = Math.min(0.9, (0.4 - noseVerticalOffset) * 2);
        } else if (noseVerticalOffset > 0.6) {
            angle = 'down';
            confidence = Math.min(0.9, (noseVerticalOffset - 0.6) * 2);
        }
        
        // Debug logging (only log occasionally to avoid spam)
        if (Math.random() < 0.1) { // Log 10% of the time
            console.log('Face Orientation Analysis:', {
                angle: angle,
                horizontalRatio: horizontalRatio.toFixed(3),
                noseVerticalOffset: noseVerticalOffset.toFixed(3),
                confidence: confidence.toFixed(2)
            });
        }
        
        // Simple lighting analysis
        const lighting = this.analyzeLighting(leftEyeCenter, rightEyeCenter, mouthCenter);
        
        return {
            angle: angle,
            confidence: confidence,
            lighting: lighting,
            horizontalRatio: horizontalRatio,
            noseVerticalOffset: noseVerticalOffset,
            debug: {
                eyeDistance: eyeDistance,
                noseOffset: noseOffset,
                faceHeight: faceHeight
            }
        };
    }

    getAveragePoint(points) {
        let sumX = 0, sumY = 0;
        points.forEach(point => {
            sumX += point.x;
            sumY += point.y;
        });
        return {
            x: sumX / points.length,
            y: sumY / points.length
        };
    }

    analyzeLighting(leftEye, rightEye, mouth) {
        // Simple lighting analysis based on landmark positions
        // In a real implementation, you'd analyze the brightness of different face regions
        return 'adequate'; // Simplified for now
    }

    clearDetectionOverlay() {
        if (this.detectionCtx) {
            this.detectionCtx.clearRect(0, 0, this.detectionCanvas.width, this.detectionCanvas.height);
        }
    }
}