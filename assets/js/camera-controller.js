/**
 * Camera Control Module
 * Handles webcam initialization and photo capture
 */

class CameraController {
    constructor() {
        this.video = null;
        this.canvas = null;
        this.stream = null;
        this.isInitialized = false;
    }

    async initialize() {
        this.video = document.getElementById('video');
        this.canvas = document.getElementById('canvas');
        
        await this.initWebcam();
    }

    async initWebcam() {
        try {
            console.log('Initializing webcam...');
            
            this.stream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                } 
            });
            
            this.video.srcObject = this.stream;
            
            return new Promise((resolve) => {
                this.video.onloadedmetadata = () => {
                    console.log('Webcam initialized successfully');
                    this.isInitialized = true;
                    this.syncCanvasElements();
                    resolve();
                };
            });
            
        } catch (error) {
            console.error('Camera initialization failed:', error);
            this.showCameraError(error);
            throw error;
        }
    }

    showCameraError(error) {
        const videoElement = document.getElementById('video');
        const errorMsg = document.createElement('div');
        errorMsg.style.cssText = 'position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:rgba(255,0,0,0.8); color:white; padding:20px; border-radius:10px; text-align:center;';
        
        if (error.name === 'NotAllowedError') {
            errorMsg.innerHTML = '📷 Camera access denied<br>Please allow camera permissions and refresh the page';
        } else if (error.name === 'NotFoundError') {
            errorMsg.innerHTML = '📷 No camera found<br>Please connect a camera and refresh the page';
        } else {
            errorMsg.innerHTML = '📷 Camera error<br>Please check your camera and refresh the page';
        }
        
        videoElement.parentElement.appendChild(errorMsg);
    }

    capturePhoto() {
        return new Promise((resolve, reject) => {
            // Check if video is ready
            if (!this.isInitialized || this.video.readyState !== this.video.HAVE_ENOUGH_DATA) {
                reject(new Error('Camera is not ready yet. Please wait a moment and try again.'));
                return;
            }
            
            // Check if video has valid dimensions
            if (this.video.videoWidth === 0 || this.video.videoHeight === 0) {
                reject(new Error('Camera video is not loaded properly. Please refresh the page and try again.'));
                return;
            }

            // Try ImageCapture API first (modern browsers)
            if ('ImageCapture' in window && this.stream) {
                this.captureWithImageCapture()
                    .then(resolve)
                    .catch(() => {
                        // Fallback to canvas method
                        this.captureWithCanvas()
                            .then(resolve)
                            .catch(reject);
                    });
            } else {
                // Fallback to canvas method
                this.captureWithCanvas()
                    .then(resolve)
                    .catch(reject);
            }
        });
    }

    captureWithImageCapture() {
        return new Promise((resolve, reject) => {
            try {
                let track = this.stream.getVideoTracks()[0];
                let imageCapture = new ImageCapture(track);
                
                imageCapture.grabFrame()
                    .then(imageBitmap => {
                        console.log('ImageCapture successful, bitmap size:', imageBitmap.width, 'x', imageBitmap.height);
                        
                        // Create a new canvas for this capture
                        let captureCanvas = document.createElement('canvas');
                        captureCanvas.width = imageBitmap.width;
                        captureCanvas.height = imageBitmap.height;
                        
                        let context = captureCanvas.getContext('2d');
                        context.drawImage(imageBitmap, 0, 0);
                        
                        let dataURL = captureCanvas.toDataURL('image/png');
                        console.log('ImageCapture dataURL length:', dataURL.length);
                        
                        if (dataURL && dataURL !== 'data:,' && dataURL.length > 100) {
                            resolve(dataURL);
                        } else {
                            reject(new Error('ImageCapture produced invalid dataURL'));
                        }
                    })
                    .catch(reject);
                    
            } catch (error) {
                reject(error);
            }
        });
    }

    captureWithCanvas() {
        return new Promise((resolve, reject) => {
            try {
                console.log('Using Canvas capture method...');
                
                // Create a fresh canvas to avoid taint issues
                let captureCanvas = document.createElement('canvas');
                captureCanvas.width = this.video.videoWidth;
                captureCanvas.height = this.video.videoHeight;
                
                let context = captureCanvas.getContext('2d');
                
                console.log('Canvas dimensions set to:', captureCanvas.width, 'x', captureCanvas.height);
                
                // Draw the current video frame to canvas
                context.drawImage(this.video, 0, 0, captureCanvas.width, captureCanvas.height);
                
                // Try different export methods
                let dataURL = null;
                
                // Method 1: PNG
                try {
                    dataURL = captureCanvas.toDataURL('image/png');
                    console.log('PNG export successful, length:', dataURL.length);
                } catch (e) {
                    console.error('PNG export failed:', e);
                }
                
                // Method 2: JPEG if PNG failed
                if (!dataURL || dataURL === 'data:,' || dataURL.length < 100) {
                    try {
                        dataURL = captureCanvas.toDataURL('image/jpeg', 0.9);
                        console.log('JPEG export successful, length:', dataURL.length);
                    } catch (e) {
                        console.error('JPEG export failed:', e);
                    }
                }
                
                // Method 3: Default if both failed
                if (!dataURL || dataURL === 'data:,' || dataURL.length < 100) {
                    try {
                        dataURL = captureCanvas.toDataURL();
                        console.log('Default export successful, length:', dataURL.length);
                    } catch (e) {
                        console.error('Default export failed:', e);
                    }
                }
                
                if (dataURL && dataURL !== 'data:,' && dataURL.length > 100) {
                    resolve(dataURL);
                } else {
                    reject(new Error('All canvas export methods failed. This may be due to browser security restrictions.'));
                }
                
            } catch (error) {
                reject(error);
            }
        });
    }

    getVideoElement() {
        return this.video;
    }

    getCanvasElement() {
        return this.canvas;
    }

    isReady() {
        return this.isInitialized && 
               this.video.readyState === this.video.HAVE_ENOUGH_DATA &&
               this.video.videoWidth > 0 && 
               this.video.videoHeight > 0;
    }

    syncCanvasElements() {
        // Sync the hidden canvas size with video
        if (this.canvas && this.video) {
            this.canvas.width = this.video.videoWidth;
            this.canvas.height = this.video.videoHeight;
        }
        
        // Trigger detection canvas sync if available
        if (window.faceApp && window.faceApp.faceDetection) {
            window.faceApp.faceDetection.syncCanvasSize();
        }
    }

    cleanup() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
        }
    }
}