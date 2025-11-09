/**
 * Face Registration Application
 * Main application controller that coordinates all modules
 */

class FaceRegistrationApp {
    constructor() {
        this.camera = new CameraController();
        this.faceDetection = new FaceDetection();
        this.capturedPhotos = [];
        this.currentStep = 0;
        this.currentFaceData = null;
        this.faceDetectionInterval = null;
        
        // Face capture angles
        this.angles = [
            { angle: 'front', title: 'Face Forward', instruction: 'Look directly at the camera with a neutral expression' },
            { angle: 'left', title: 'Turn Left', instruction: 'Turn your head slightly to the left (your left)' },
            { angle: 'right', title: 'Turn Right', instruction: 'Turn your head slightly to the right (your right)' },
            { angle: 'up', title: 'Look Up', instruction: 'Tilt your head slightly upward' },
            { angle: 'down', title: 'Look Down', instruction: 'Tilt your head slightly downward' }
        ];
        
        // UI elements
        this.elements = {};
    }

    async initialize() {
        this.initializeElements();
        this.bindEvents();
        
        try {
            // Initialize camera first
            await this.camera.initialize();
            
            // Initialize face detection after camera is ready
            if (typeof faceapi !== 'undefined') {
                await this.faceDetection.initialize();
                this.updateStatus('✓ AI Face detection ready');
            } else {
                this.updateStatus('⚠️ Using basic face detection');
                this.faceDetection.setupDetectionCanvas();
            }
            
            this.startFaceDetection();
            this.updateAngleGuide();
            
        } catch (error) {
            console.error('Initialization failed:', error);
            this.updateStatus('❌ System initialization failed');
        }
    }

    initializeElements() {
        this.elements = {
            video: document.getElementById('video'),
            canvas: document.getElementById('canvas'),
            captureBtn: document.getElementById('capture-btn'),
            skipBtn: document.getElementById('skip-btn'),
            currentAngle: document.getElementById('current-angle'),
            angleInstruction: document.getElementById('angle-instruction'),
            photoThumbnails: document.getElementById('photo-thumbnails'),
            facePhotosInput: document.getElementById('face_photos'),
            faceStatus: document.getElementById('face-status'),
            orientationStatus: document.getElementById('orientation-status'),
            lightingStatus: document.getElementById('lighting-status'),
            guidanceMessage: document.getElementById('guidance-message'),
            submitBtn: document.getElementById('submit-btn')
        };
    }

    bindEvents() {
        this.elements.captureBtn.addEventListener('click', () => this.capturePhoto());
        this.elements.skipBtn.addEventListener('click', () => this.skipPhoto());
        
        // Form submission validation
        document.querySelector('form').addEventListener('submit', (e) => this.handleFormSubmit(e));
    }

    startFaceDetection() {
        console.log('Starting face detection...');
        
        // Clear any existing interval
        if (this.faceDetectionInterval) {
            clearInterval(this.faceDetectionInterval);
        }
        
        if (this.faceDetection.faceApiLoaded) {
            this.updateStatus('🤖 AI-Enhanced face detection active');
            this.faceDetectionInterval = setInterval(() => this.detectFaceWithAI(), 200);
        } else {
            this.updateStatus('👤 Basic face detection active');
            this.faceDetectionInterval = setInterval(() => this.detectFaceBasic(), 500);
        }
    }

    async detectFaceWithAI() {
        try {
            const detection = await this.faceDetection.detectFaceWithFaceAPI(this.elements.video);
            
            if (detection) {
                this.currentFaceData = detection;
                this.faceDetection.drawFaceDetectionWithLandmarks(detection);
                
                const analysis = this.faceDetection.analyzeFaceOrientation(detection.landmarks);
                this.updateFaceStatus(detection, analysis);
                this.updateGuidance(detection, analysis);
                
            } else {
                this.currentFaceData = null;
                this.faceDetection.clearDetectionOverlay();
                this.updateStatus('👤 Looking for face...');
                this.elements.orientationStatus.textContent = '📐 Orientation: Unknown';
                this.elements.lightingStatus.textContent = '💡 Lighting: Unknown';
                this.elements.guidanceMessage.textContent = 'Position your face in the camera view';
            }
        } catch (error) {
            console.warn('AI face detection error:', error);
            this.detectFaceBasic();
        }
    }

    detectFaceBasic() {
        const faceRegion = this.faceDetection.detectFaceBasic(this.elements.video, this.elements.canvas);
        
        if (faceRegion) {
            this.currentFaceData = { box: faceRegion, confidence: faceRegion.confidence };
            this.faceDetection.drawBasicFaceDetection(faceRegion);
            this.updateStatus(`👤 Face detected (${Math.round(faceRegion.confidence * 100)}% confidence)`);
            this.elements.orientationStatus.textContent = '📐 Orientation: Use guidance below';
            this.elements.lightingStatus.textContent = '💡 Lighting: Ensure good lighting';
            this.updateBasicGuidance();
        } else {
            this.currentFaceData = null;
            this.faceDetection.clearDetectionOverlay();
            this.updateStatus('👤 Looking for face...');
            this.elements.orientationStatus.textContent = '📐 Orientation: Unknown';
            this.elements.lightingStatus.textContent = '💡 Lighting: Unknown';
            this.elements.guidanceMessage.textContent = 'Position your face in the camera view';
        }
    }

    updateFaceStatus(detection, analysis) {
        const confidence = Math.round(detection.confidence * 100);
        this.updateStatus(`🤖 Face detected (${confidence}% confidence)`);
        this.elements.orientationStatus.textContent = `📐 Orientation: ${analysis.angle} (${Math.round(analysis.confidence * 100)}%)`;
        this.elements.lightingStatus.textContent = `💡 Lighting: ${analysis.lighting}`;
    }

    updateGuidance(detection, analysis) {
        if (this.currentStep >= this.angles.length) {
            this.elements.guidanceMessage.textContent = 'All photos captured! You can submit the form.';
            return;
        }

        const targetAngle = this.angles[this.currentStep].angle;
        const detectedAngle = analysis.angle;
        
        if (this.checkAngleMatch(detectedAngle, targetAngle)) {
            this.elements.guidanceMessage.innerHTML = `✅ <strong>Perfect! Ready to capture ${targetAngle} angle</strong>`;
            this.elements.guidanceMessage.style.color = 'green';
            this.elements.captureBtn.style.backgroundColor = '#28a745';
        } else {
            this.elements.guidanceMessage.innerHTML = `➡️ ${this.angles[this.currentStep].instruction}<br><small style="color:#666;">Currently detected: ${detectedAngle}</small>`;
            this.elements.guidanceMessage.style.color = '#333';
            this.elements.captureBtn.style.backgroundColor = '';
        }
    }

    updateBasicGuidance() {
        if (this.currentStep >= this.angles.length) {
            this.elements.guidanceMessage.textContent = 'All photos captured! You can submit the form.';
            return;
        }

        this.elements.guidanceMessage.innerHTML = `📷 ${this.angles[this.currentStep].instruction}`;
        this.elements.guidanceMessage.style.color = '#333';
    }

    updateStatus(message) {
        this.elements.faceStatus.textContent = message;
    }

    async capturePhoto() {
        // Check if face detection conditions are good
        if (!this.currentFaceData) {
            alert('No face detected. Please position your face in the camera view.');
            return;
        }

        try {
            const dataURL = await this.camera.capturePhoto();
            this.processSuccessfulCapture(dataURL);
        } catch (error) {
            console.error('Photo capture failed:', error);
            alert('Photo capture failed: ' + error.message);
        }
    }

    processSuccessfulCapture(dataURL) {
        console.log('Processing successful capture...');
        
        if (this.currentStep < this.angles.length) {
            // Add new photo
            this.capturedPhotos.push({
                dataURL: dataURL,
                angle: this.angles[this.currentStep].angle,
                step: this.currentStep + 1
            });
            
            this.createThumbnail(this.angles[this.currentStep].angle, this.currentStep + 1);
            this.currentStep++;
        } else {
            // Replace last photo (retake)
            let lastIndex = this.capturedPhotos.length - 1;
            this.capturedPhotos[lastIndex].dataURL = dataURL;
            this.updateLastThumbnail();
        }

        // Update form input
        this.elements.facePhotosInput.value = JSON.stringify(this.capturedPhotos.map(photo => ({
            dataURL: photo.dataURL,
            angle: photo.angle,
            step: photo.step
        })));

        this.updateAngleGuide();
    }

    createThumbnail(angle, step) {
        const thumbnailDiv = document.createElement('div');
        thumbnailDiv.className = 'photo-thumbnail';
        thumbnailDiv.innerHTML = `
            <div class="thumbnail-placeholder">
                📷 Photo ${step}<br>
                <small>${angle} angle</small>
            </div>
        `;
        this.elements.photoThumbnails.appendChild(thumbnailDiv);
    }

    updateLastThumbnail() {
        const thumbnails = this.elements.photoThumbnails.children;
        if (thumbnails.length > 0) {
            const lastThumbnail = thumbnails[thumbnails.length - 1];
            lastThumbnail.innerHTML = `
                <div class="thumbnail-placeholder">
                    📷 Photo ${thumbnails.length} (Updated)<br>
                    <small>${this.angles[thumbnails.length - 1].angle} angle</small>
                </div>
            `;
        }
    }

    skipPhoto() {
        if (this.currentStep < this.angles.length) {
            this.currentStep++;
            this.updateAngleGuide();
        }
    }

    updateAngleGuide() {
        if (this.currentStep < this.angles.length) {
            this.elements.currentAngle.textContent = `Step ${this.currentStep + 1} of ${this.angles.length}: ${this.angles[this.currentStep].title}`;
            this.elements.angleInstruction.textContent = this.angles[this.currentStep].instruction;
            this.elements.captureBtn.textContent = 'Capture Photo';
            this.elements.captureBtn.disabled = false;
        } else {
            this.elements.currentAngle.textContent = 'All photos captured!';
            this.elements.angleInstruction.textContent = 'You can now submit the form or retake the last photo.';
            this.elements.captureBtn.textContent = 'Retake Last Photo';
            this.elements.submitBtn.disabled = false;
        }
    }

    checkAngleMatch(detectedAngle, targetAngle) {
        // Direct match
        if (detectedAngle === targetAngle) {
            return true;
        }
        
        // For up and down angles, front view is also acceptable
        // (vertical angles are harder to detect precisely)
        if ((targetAngle === 'up' || targetAngle === 'down') && detectedAngle === 'front') {
            return true;
        }
        
        return false;
    }

    getOrientationGuidance(target, current) {
        switch (target) {
            case 'front':
                return 'Look straight at the camera';
            case 'left':
                if (current === 'right') return 'Turn your head more to the left';
                return 'Turn your head to the left';
            case 'right':
                if (current === 'left') return 'Turn your head more to the right';
                return 'Turn your head to the right';
            case 'up':
                if (current === 'down') return 'Tilt your head up more';
                return 'Tilt your head upward';
            case 'down':
                if (current === 'up') return 'Tilt your head down more';
                return 'Tilt your head downward';
            default:
                return 'Follow the instruction above';
        }
    }

    handleFormSubmit(e) {
        if (this.capturedPhotos.length === 0) {
            e.preventDefault();
            alert('Please capture at least one face photo before submitting.');
            return false;
        }
    }
}

// Initialize application when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.faceApp = new FaceRegistrationApp();
    window.faceApp.initialize().catch(console.error);
});