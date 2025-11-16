<?php
require '../../db_connection.php';

class EmployeeProcessor{
    private $db;
    private $errors = [];
    private $validatedData=[];
    private $validationRules = [
        'employee_id' =>['required', 'alphanumeric', 'max:20', 'unique:employees'],
        'first_name' => ['required', 'string', 'max:50'],
        'middle_name' => ['optional', 'string', 'max:50'],
        'last_name' => ['required', 'string', 'max:50'],
        'email' => ['optional', 'email', 'max:100', 'unique:employees'],
        'phone' => ['required', 'phone', 'max:15'],  // FIXED: Changed from 'string' to 'phone'
        'roles' => ['required', 'custom_role_validation', 'max:100'],
        'department' => ['required', 'string', 'max:100'],
        'position' => ['required', 'string', 'max:100'],
        'hire_date' => ['required', 'date', 'before_or_equal:today'],
        'add_password' => ['optional', 'string', 'max:255'],
        'face_photos' => ['optional', 'json'],
        'schedule_data' => ['optional', 'json'],
        'designate_class' => ['optional', 'string', 'max:50'],
        'designate_subject' => ['optional', 'string', 'max:50'],
        'room-number' => ['optional', 'string', 'max:20'],
    ];

    public function __construct($database){
        $this->db = $database;
    }

    public function handleRequest(){
        // Log the start of request
        $this->logActivity('Employee addition request started', 'POST request received');
        

        
        try{
            if(!$this->validateCSRFToken()){
                $this->logSecurityEvent('CSRF token validation failed');
                $this->sendErrorResponse('Invalid CSRF token.', 403);
                return;
            }
            if(!$this->checkRateLimits()){
                $this->logError('Rate Limit Exceeded', 'User exceeded rate limit');
                $this->sendErrorResponse('Rate limit exceeded. Please try again later.', 429);
                return;
            }
            if(!$this->validateAllInputs()){
                $this->logError('Validation Failed', 'Input validation failed with ' . count($this->errors) . ' errors');
                $this->sendErrorResponse('Validation errors occurred.', 400, $this->errors);
                return;
            }

            $result = $this->insertEmployee();
            if($result['success']){
                $this->logActivity('Employee added successfully', 'Employee ID: '. $this->validatedData['employee_id']);
                $this->sendSuccessResponse($result);
            }else{
                $this->logError('Database Error', 'Failed to insert employee: ' . $result['message']);
                $this->sendErrorResponse($result['message'], 500); 
            }

        } catch (Exception $e) {
            $this->logError('Unexpected Error', $e->getMessage());
            $this->sendErrorResponse('An unexpected error occurred.', 500);
        }
    }

    private function validateCSRFToken(){
        $submitted_token = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($submitted_token) || empty($sessionToken)){
            return false;
        }
        return hash_equals($sessionToken, $submitted_token);
    }

    private function validateAllInputs(){
        foreach ($this->validationRules as $field => $rules){
            $value = $_POST[$field] ?? '';

            // FIXED: Skip validation for optional empty fields
            if(in_array('optional', $rules) && empty($value)){
                continue;
            }

            // FIXED: Check if required field is empty
            if(in_array('required', $rules) && empty($value)){
                $error_msg = ucfirst(str_replace('_',' ', $field))." is required.";
                $this->errors[$field][] = $error_msg;
                $this->logValidationError($field, $error_msg, $value);
                continue;
            }

            $this->validateField($field, $value, $rules);
        }
        
        // Ensure face_photos is included in validated data even if optional
        if (isset($_POST['face_photos']) && !empty($_POST['face_photos'])) {
            $this->validatedData['face_photos'] = $_POST['face_photos'];
        }
        
        // Ensure schedule_data is included in validated data even if optional
        if (isset($_POST['schedule_data']) && !empty($_POST['schedule_data'])) {
            $this->validatedData['schedule_data'] = $_POST['schedule_data'];
        }
        
        // Ensure faculty-specific fields are included in validated data even if optional
        if (isset($_POST['designate_class']) && !empty($_POST['designate_class'])) {
            $this->validatedData['designate_class'] = $_POST['designate_class'];
        }
        if (isset($_POST['designate_subject']) && !empty($_POST['designate_subject'])) {
            $this->validatedData['designate_subject'] = $_POST['designate_subject'];
        }
        if (isset($_POST['room-number']) && !empty($_POST['room-number'])) {
            $this->validatedData['room-number'] = $_POST['room-number'];
        }

        return empty($this->errors);
    }

    private function validateDate($date){
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    private function checkUnique($field, $value, $table){
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
        $stmt->bind_param('s', $value);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_row()[0];
        return $count == 0;
    }

    private function sanitizeInput($value,$field){
        $value = trim($value);
        
        switch($field){
            case 'email':
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                return filter_var($value, FILTER_SANITIZE_EMAIL);
            case 'phone':
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                return preg_replace('/[^\d\+\-\(\)\s]/', '', $value);  // FIXED: Allow more phone characters
            case 'roles':
                // Special sanitization for roles - preserve underscores and hyphens but escape HTML
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                // Replace multiple spaces with single spaces and trim
                $value = preg_replace('/\s+/', ' ', $value);
                return trim($value);
            case 'face_photos':
            case 'schedule_data':
                // Don't sanitize JSON data as it would corrupt base64 content or schedule structure
                return $value;
            default:
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                return $value;
        }
    }

    private function validateField($field, $value, $rules){
        foreach ($rules as $rule){
            if (strpos($rule, ':') !== false){
                [$ruleName, $ruleValue] = explode(':', $rule, 2);
            }else{
                $ruleName = $rule;
                $ruleValue = null;
            }

            switch ($ruleName){
                case 'required':
                    if (empty($value)){
                        $this->errors[$field][] = ucfirst(str_replace('_',' ', $field))." is required.";
                        return;
                    }
                    break;
                case 'optional':
                    // Skip validation if field is optional and empty
                    break;
                case 'string':
                    if(!is_string($value)||!preg_match('/^[a-zA-Z\s\-\.\']+$/', $value)){
                        $error_msg = ucfirst(str_replace('_',' ', $field))." must contain only letters, spaces, hyphens, dots, and apostrophes.";
                        $this->errors[$field][] = $error_msg;
                        $this->logValidationError($field, $error_msg, $value);
                    }
                    break;
                case 'alphanumeric':
                    if(!preg_match('/^[a-zA-Z0-9]+$/', $value)){
                        $error_msg = ucfirst(str_replace('_',' ', $field))." must contain only letters and numbers.";
                        $this->errors[$field][] = $error_msg;
                        $this->logValidationError($field, $error_msg, $value);
                    }
                    break;
                case 'email':
                    if(!empty($value)&&!filter_var($value, FILTER_VALIDATE_EMAIL)){
                        $error_msg = "Please enter a valid email address.";
                        $this->errors[$field][] = $error_msg;
                        $this->logValidationError($field, $error_msg, $value);
                    }
                    break;
                case 'phone':
                    // FIXED: Proper phone validation
                    if(!preg_match('/^[\+]?[0-9\-\(\)\s]{7,15}$/', $value)){
                        $error_msg = "Please enter a valid phone number (7-15 digits, may include +, -, (), spaces).";
                        $this->errors[$field][] = $error_msg;
                        $this->logValidationError($field, $error_msg, $value);
                    }
                    break;
                case 'date':
                    if(!$this->validateDate($value)){
                        $error_msg = "Please enter a valid date (YYYY-MM-DD).";
                        $this->errors[$field][] = $error_msg;
                        $this->logValidationError($field, $error_msg, $value);
                    }
                    break;
                case 'max':
                    if(strlen($value) > (int)$ruleValue){
                        $error_msg = ucfirst(str_replace('_',' ', $field))." must not exceed ".$ruleValue." characters.";
                        $this->errors[$field][] = $error_msg;
                        $this->logValidationError($field, $error_msg, $value);
                    }
                    break;
                case 'in':
                    $allowedValue = explode(',', $ruleValue);
                    // DEBUG: Log the validation details
                    $this->logActivity('Role validation debug', "Field: $field, Value: '$value', Allowed: " . json_encode($allowedValue));
                    if(!in_array($value, $allowedValue)){
                        $error_msg = 'Invalid ' . str_replace('_',' ', $field)." selection.";
                        $this->errors[$field][] = $error_msg;
                        $this->logValidationError($field, $error_msg, $value);
                    }
                    break;
                case 'custom_role_validation':
                    // Allow any valid role string, including new ones
                    if(!preg_match('/^[a-zA-Z0-9\s\-_]{2,100}$/', $value)){
                        $error_msg = 'Role must be 2-100 characters and contain only letters, numbers, spaces, hyphens, and underscores.';
                        $this->errors[$field][] = $error_msg;
                        $this->logValidationError($field, $error_msg, $value);
                    } else {
                        // Log when a new role is being added
                        $defaultRoles = ['Administrator', 'Faculty_Member', 'Non-Teaching_Personnel'];
                        if (!in_array($value, $defaultRoles)) {
                            $this->logActivity('New role added', "Role: '$value' by Employee ID: " . ($_POST['employee_id'] ?? 'Unknown'));
                        }
                    }
                    break;
                case 'unique':
                    if(!$this->checkUnique($field, $value, $ruleValue)){
                        $error_msg = ucfirst(str_replace('_',' ', $field))." already exists.";
                        $this->errors[$field][] = $error_msg;
                        $this->logValidationError($field, $error_msg, $value);
                    }
                    break;
                case 'before_or_equal':
                    if($ruleValue === 'today' && strtotime($value) > strtotime('today')){
                        $error_msg = "Hire date cannot be in the future.";
                        $this->errors[$field][] = $error_msg;
                        $this->logValidationError($field, $error_msg, $value);
                    }
                    break;
                case 'json':
                    if(!empty($value)){
                        $decoded = json_decode($value, true);
                        if(json_last_error() !== JSON_ERROR_NONE){
                            $error_msg = "Invalid face photos data.";
                            $this->errors[$field][] = $error_msg;
                            $this->logValidationError($field, $error_msg, $value);
                        }
                    }
                    break;
            }
        }
        
        if (!isset($this->errors[$field])){
            $this->validatedData[$field]=$this->sanitizeInput($value,$field);
        }
    }

    private function insertEmployee(){
        try{
            $this->db->begin_transaction();
            
            // Set default values
            $middle_name = $this->validatedData['middle_name'] ?? '';
            $email = $this->validatedData['email'] ?? '';
            $default_profile_pic = 'assets/profile_pic/user.png';
            
            // Use provided password or fall back to employee_id as default
            $password = $this->validatedData['add_password'] ?? $this->validatedData['employee_id'];
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Log password generation for debugging
            $password_source = isset($this->validatedData['add_password']) ? 'custom password' : 'employee ID';
            $this->logActivity('Password Generated', "Employee ID: {$this->validatedData['employee_id']}, Password set from {$password_source} (hashed)");
            
            // FIXED: Added employee_password column
            $stmt = $this->db->prepare("
                INSERT INTO employees(
                    employee_id, employee_password, first_name, middle_name, last_name,
                    email, phone, roles, department, position, hire_date, profile_photo
                ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
                
            $stmt->bind_param('ssssssssssss',
                $this->validatedData['employee_id'],
                $hashed_password,
                $this->validatedData['first_name'],
                $middle_name,
                $this->validatedData['last_name'],
                $email,
                $this->validatedData['phone'],
                $this->validatedData['roles'],
                $this->validatedData['department'],
                $this->validatedData['position'],
                $this->validatedData['hire_date'],
                $default_profile_pic
            );
            
            $result = $stmt->execute();

            if(!$result){
                throw new Exception('Failed to insert employee record.');
            }
            
            $employee_id = $this->db->insert_id;
            
            // Process face photos if provided
            $facePhotosProcessed = false;
            if(!empty($this->validatedData['face_photos'])){
                $this->processFacePhotos($this->validatedData['employee_id'], $this->validatedData['first_name'], $this->validatedData['last_name']);
                $facePhotosProcessed = true;
            }
            
            // Process schedule data if provided
            if(!empty($this->validatedData['schedule_data'])){
                $this->processScheduleData($employee_id);
            }
            
            // Commit the transaction first to ensure employee record is saved
            $this->db->commit();
            
            // Generate face embeddings AFTER transaction commit
            // This is done outside the transaction because:
            // 1. Python script execution can take time
            // 2. If embedding generation fails, we still have the employee record
            // 3. Embeddings can be regenerated later if needed
            if ($facePhotosProcessed) {
                $this->logActivity('Starting Face Embedding Generation', "Employee ID: {$this->validatedData['employee_id']}, DB ID: $employee_id");
                
                // Generate embeddings (this calls Python script which saves to DB)
                $embeddingResult = $this->generateFaceEmbeddings($employee_id, $this->validatedData['employee_id']);
                
                if ($embeddingResult) {
                    $this->logActivity('Face Embeddings Generated Successfully', "Employee DB ID: $employee_id");
                } else {
                    // Log warning but don't fail the employee creation
                    $this->logError('Face Embedding Generation Warning', "Failed to generate embeddings for employee $employee_id. Employee record was created successfully. Embeddings can be regenerated manually.");
                }
            }
            
            return[
                'success' => true,
                'employee_id' => $employee_id,
                'message' => 'Employee added successfully.'
            ];
        }
        catch (Exception $e){
            $this->db->rollback();
            return[
                'success' => false,
                'message' => 'Error adding employee: '.$e->getMessage()
            ];
        }
    }

    private function checkRateLimits(){
        $ip = $_SERVER['REMOTE_ADDR'];
        $currentTime = time();
        
        // Create logs directory in parent folder
        $log_dir = dirname(__DIR__) . '/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $rateLimitFile = $log_dir . 'rate_limits_' . md5($ip) . '.txt';
        
        if(file_exists($rateLimitFile)){
            $attempts = json_decode(file_get_contents($rateLimitFile), true);
            $attempts = array_filter($attempts, function($timestamp) use ($currentTime){
                return ($currentTime - $timestamp) < 300;
            });

            if(count($attempts) >= 10){
                return false;
            }
        }else{
            $attempts = [];
        }
        
        $attempts[] = $currentTime;
        file_put_contents($rateLimitFile, json_encode($attempts));
        
        return true;
    }

    private function logSecurityEvent($event, $data = []) {
        $log_dir = dirname(__DIR__) . '/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $data_str = !empty($data) ? json_encode($data) : 'No additional data';
        
        $log_entry = "[{$timestamp}] [SECURITY] [IP: {$ip}] {$event} - {$data_str}" . PHP_EOL;
        
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }

    private function logActivity($activity, $reference = '') {
        $log_dir = dirname(__DIR__) . '/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $ref_str = !empty($reference) ? " - {$reference}" : '';
        
        $log_entry = "[{$timestamp}] [ACTIVITY] [IP: {$ip}] {$activity}{$ref_str}" . PHP_EOL;
        
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }

    private function logError($context, $message) {
        $log_dir = dirname(__DIR__) . '/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $file = basename(__FILE__);
        
        $log_entry = "[{$timestamp}] [ERROR] [IP: {$ip}] [{$file}] Context: {$context} - Message: {$message}" . PHP_EOL;
        
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }

    private function logValidationError($field, $error_message, $value) {
        $log_dir = dirname(__DIR__) . '/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $log_entry = "[{$timestamp}] [VALIDATION ERROR] [IP: {$ip}] Field: {$field} - Value: '{$value}' - Error: {$error_message}" . PHP_EOL;
        
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }

    private function sendSuccessResponse($data) {
        // Store success message in session
        $_SESSION['success_message'] = $data['message'];
        
        // Log to file (not to output)
        $log_dir = dirname(__DIR__) . '/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        $log_entry = "[" . date('Y-m-d H:i:s') . "] Employee Addition Success: " . $data['message'] . " - Employee ID: " . ($data['employee_id'] ?? 'Unknown') . PHP_EOL;
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
        
        // Use JavaScript redirect to avoid header issues
        echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <script>
        window.location.href = "../staff.php";
    </script>
</head>
<body>
    <p>Redirecting...</p>
</body>
</html>';
        exit;
    }

    private function processFacePhotos($employeeId, $firstName, $lastName) {
        $facePhotosJson = $this->validatedData['face_photos'] ?? '';
        
        if (empty($facePhotosJson)) {
            return;
        }
        
        $facePhotos = json_decode($facePhotosJson, true);
        if (!$facePhotos || !is_array($facePhotos)) {
            return;
        }
        
        // Create uploads directory if it doesn't exist
        $uploadDir = dirname(__DIR__) . '/uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        foreach ($facePhotos as $index => $photoData) {
            if (isset($photoData['dataURL']) && isset($photoData['angle'])) {
                $this->saveFacePhoto($photoData['dataURL'], $employeeId, $firstName, $lastName, $photoData['angle'], $index + 1);
            }
        }
    }
    
    private function saveFacePhoto($dataURL, $employeeId, $firstName, $lastName, $angle, $angleNumber) {
        // Extract base64 data
        if (preg_match('/^data:image\/(\w+);base64,/', $dataURL, $matches)) {
            $imageType = $matches[1];
            $base64Data = substr($dataURL, strpos($dataURL, ',') + 1);
            $imageData = base64_decode($base64Data);
            
            if ($imageData === false) {
                $this->logError('Face Photo Processing', "Failed to decode base64 image for employee: $employeeId");
                return false;
            }
            
            // Generate filename: ID_firstname_lastname_anglenumber.ext
            $filename = $employeeId . '_' . $firstName . '_' . $lastName . '_' . $angleNumber . '.' . $imageType;
            $filepath = dirname(__DIR__) . '/uploads/' . $filename;
            
            // Save the file
            if (file_put_contents($filepath, $imageData)) {
                $this->logActivity('Face Photo Saved', "File: $filename, Angle: $angle, Employee: $employeeId");
                return true;
            } else {
                $this->logError('Face Photo Processing', "Failed to save image file: $filename");
                return false;
            }
        }
        
        return false;
    }

    private function generateFaceEmbeddings($dbEmployeeId, $employeeId) {
        /**
         * Generate face embeddings from uploaded photos using Python script.
         */
        
        $this->logActivity('Face Embedding Generation Started', "Employee ID: $employeeId, DB ID: $dbEmployeeId");
        
        // Path to Python script in staffmanagement folder
        $scriptPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'generate_face_embeddings.py';
        
        if (!file_exists($scriptPath)) {
            $this->logError('Face Embedding Generation', "Python script not found at: $scriptPath");
            return false;
        }
        
        // Database credentials
        $dbHost = 'localhost';
        $dbUser = 'root';
        $dbPassword = 'Confirmp@ssword123';
        $dbName = 'database_records';
        
        // Get Python executable path
        $pythonExe = $this->findPythonExecutable();
        
        if (!$pythonExe) {
            $this->logError('Face Embedding Generation', 'Python executable not found');
            return false;
        }
        
        // Log the paths for debugging
        $this->logActivity('Python Executable Command', $pythonExe);
        $this->logActivity('Script Path', $scriptPath);
        
        // For arguments, escape them properly
        $escapedEmployeeId = escapeshellarg($employeeId);
        $escapedDbEmployeeId = escapeshellarg($dbEmployeeId);
        $escapedDbHost = escapeshellarg($dbHost);
        $escapedDbUser = escapeshellarg($dbUser);
        $escapedDbPassword = escapeshellarg($dbPassword);
        $escapedDbName = escapeshellarg($dbName);
        
        // Set working directory to staffmanagement folder (where script is located)
        $workingDir = dirname(__DIR__);
        $oldDir = getcwd();
        chdir($workingDir);
        
        // Check if we found the venv Python
        $isVenvPython = (strpos($pythonExe, '.venv') !== false || strpos($pythonExe, 'env' . DIRECTORY_SEPARATOR) !== false);
        
        // Don't add quotes here - we'll add them properly in the command construction
        $commandArgs = $escapedEmployeeId . ' ' . $escapedDbEmployeeId . ' ' .
                       $escapedDbHost . ' ' . $escapedDbUser . ' ' .
                       $escapedDbPassword . ' ' . $escapedDbName;
        
        $command = '';
        
        if ($isVenvPython) {
            // The VENV Python executable already knows its environment.
            // No need to set PYTHONPATH.
            $this->logActivity('Face Embedding Generation', "Using VENV Python. Skipping SET PYTHONPATH.");
            
            // FIXED: Proper quoting for Windows cmd
            // Use escapeshellarg for the entire command to avoid quote nesting issues
            $command = '"' . $pythonExe . '" "' . $scriptPath . '" ' . $commandArgs . ' 2>&1';
        } else {
            // Using System Python. We must set PYTHONPATH to find venv packages.
            $this->logActivity('Face Embedding Generation', "Using System Python. Setting PYTHONPATH.");
            // Get project root (go up from staffmanagement to EndDev)
            $projectRoot = dirname(dirname(__DIR__));
            $venvPath = $projectRoot . DIRECTORY_SEPARATOR . '.venv';
            $venvSitePackages = $venvPath . DIRECTORY_SEPARATOR . 'Lib' . DIRECTORY_SEPARATOR . 'site-packages';
            
            // FIXED: Proper quoting for environment variables and paths
            $command = 'cmd /c "SET PYTHONPATH=' . $venvSitePackages . ' && ' .
                       'SET VIRTUAL_ENV=' . $venvPath . ' && ' .
                       '"' . $pythonExe . '" "' . $scriptPath . '" ' .
                       $commandArgs . ' 2>&1"';
        }

        $this->logActivity('Executing Python Script', "Command: $command");
        
        // Execute the Python script
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        // Restore original directory
        chdir($oldDir);
        
        // Log the output
        $outputStr = implode("\n", $output);
        $this->logActivity('Python Script Output', $outputStr);
        
        // Check if execution was successful
        if ($returnCode === 0) {
            $this->logActivity('Face Embedding Generation Successful', "Employee ID: $employeeId");
            return true;
        } else {
            $this->logError('Face Embedding Generation Failed', "Return code: $returnCode, Output: $outputStr");
            return false;
        }
    }
    
    private function findPythonExecutable() {
        /**
         * Find the Python executable.
         * Searches in multiple locations for maximum flexibility across different systems.
         * * @return string|false - Path to Python executable or false if not found
         */
        
        // Get the project root (EndDev folder) - go up from processes -> staffmanagement -> EndDev
        $projectRoot = dirname(dirname(__DIR__));
        $this->logActivity('Python Detection Debug', "Project Root: $projectRoot");

        // --- START: ADDED VENV CHECK ---
        // Prioritize the virtual environment's Python executable in project root
        $venvPaths = [
            // Windows venv (most likely)
            $projectRoot . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe',
            // Linux/macOS venv
            $projectRoot . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python',
            // Check common venv name 'env' as well
            $projectRoot . DIRECTORY_SEPARATOR . 'env' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe',
            $projectRoot . DIRECTORY_SEPARATOR . 'env' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python',
        ];
        
        foreach ($venvPaths as $path) {
            $exists = file_exists($path);
            $this->logActivity('Python Detection Debug', "Checking VENV: $path | Exists: " . ($exists ? 'YES' : 'NO'));
            if ($exists) {
                $this->logActivity('Python Executable Found', "Path: $path (VENV)");
                return $path;
            }
        }
        $this->logActivity('Python Detection Debug', "VENV Python not found. Checking system paths...");
        // --- END: ADDED VENV CHECK ---

        // Strategy 1: Check environment-based paths first (your original code)
        $userProfile = getenv('USERPROFILE'); // Windows user folder
        $localAppData = getenv('LOCALAPPDATA'); // Windows local app data
        
        // Debug: Log environment variables
        $this->logActivity('Python Detection Debug', "USERPROFILE=" . ($userProfile ? $userProfile : 'NOT SET'));
        $this->logActivity('Python Detection Debug', "LOCALAPPDATA=" . ($localAppData ? $localAppData : 'NOT SET'));
        
        $pythonPaths = [];
        
        // Add user-specific paths if environment variables are available
        if ($userProfile) {
            $pythonPaths[] = $userProfile . '\\AppData\\Local\\Programs\\Python\\Python312\\python.exe';
            $pythonPaths[] = $userProfile . '\\AppData\\Local\\Programs\\Python\\Python311\\python.exe';
            $pythonPaths[] = $userProfile . '\\AppData\\Local\\Programs\\Python\\Python310\\python.exe';
            $pythonPaths[] = $userProfile . '\\AppData\\Local\\Programs\\Python\\Python39\\python.exe';
        }
        
        if ($localAppData) {
            $pythonPaths[] = $localAppData . '\\Programs\\Python\\Python312\\python.exe';
            $pythonPaths[] = $localAppData . '\\Programs\\Python\\Python311\\python.exe';
            $pythonPaths[] = $localAppData . '\\Programs\\Python\\Python310\\python.exe';
        }
        
        // Add common system-wide installation paths
        $pythonPaths[] = 'C:\\Python312\\python.exe';
        $pythonPaths[] = 'C:\\Python311\\python.exe';
        $pythonPaths[] = 'C:\\Python310\\python.exe';
        $pythonPaths[] = 'C:\\Python39\\python.exe';
        $pythonPaths[] = 'C:\\Python38\\python.exe';
        
        // Linux/Mac common paths
        $pythonPaths[] = '/usr/bin/python3';
        $pythonPaths[] = '/usr/local/bin/python3';
        $pythonPaths[] = '/usr/bin/python';
        
        // Check each path
        foreach ($pythonPaths as $path) {
            $exists = file_exists($path);
            $this->logActivity('Python Detection Debug', "Checking: $path | Exists: " . ($exists ? 'YES' : 'NO'));
            if ($exists) {
                $this->logActivity('Python Executable Found', "Path: $path");
                return $path;
            }
        }
        
        // Strategy 2: Try 'where python' command on Windows (your original code)
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec('where python 2>nul', $whereOutput, $whereReturnCode);
            if ($whereReturnCode === 0 && !empty($whereOutput)) {
                // Filter out py.exe and Windows Store stubs
                foreach ($whereOutput as $foundPath) {
                    $foundPath = trim($foundPath);
                    // Skip py.exe launcher and Windows Store stubs in System32/WindowsApps
                    if (stripos($foundPath, 'py.exe') === false && 
                        stripos($foundPath, 'WindowsApps') === false &&
                        stripos($foundPath, 'System32') === false &&
                        file_exists($foundPath)) {
                        $this->logActivity('Python Executable Found', "Path: $foundPath (via where command)");
                        return $foundPath;
                    }
                }
            }
        } else {
            // Linux/Mac: Try 'which python3' or 'which python'
            exec('which python3 2>/dev/null', $whichOutput, $whichReturnCode);
            if ($whichReturnCode === 0 && !empty($whichOutput)) {
                $pythonPath = trim($whichOutput[0]);
                if (file_exists($pythonPath)) {
                    $this->logActivity('Python Executable Found', "Path: $pythonPath (via which command)");
                    return $pythonPath;
                }
            }
        }
        
        $this->logError('Python Not Found', 'Could not find Python installation. Tried venv, environment paths, common locations, and system commands.');
        return false;
    }

    private function processScheduleData($employeeId) {
        $scheduleDataJson = $this->validatedData['schedule_data'] ?? '';
        
        if (empty($scheduleDataJson)) {
            return;
        }
        
        $scheduleData = json_decode($scheduleDataJson, true);
        if (!$scheduleData || !is_array($scheduleData)) {
            $this->logError('Schedule Processing', 'Invalid schedule data JSON');
            return;
        }
        
        // --- Refactored Logic ---
        // Create ONE schedule template for the employee that holds all periods.
        $scheduleName = "Schedule_" . $this->validatedData['employee_id'] . "_" . date('Ymd');
        $description = "Auto-generated schedule for " . $this->validatedData['first_name'] . " " . $this->validatedData['last_name'];

        // 1. Create a single, overarching schedule template.
        $stmt = $this->db->prepare("
            INSERT INTO schedules (schedule_name, description) 
            VALUES (?, ?)
        ");
        $stmt->bind_param('ss', $scheduleName, $description);
        $stmt->execute();
        $mainScheduleId = $this->db->insert_id;

        if (!$mainScheduleId) {
            throw new Exception('Failed to create schedule template');
        }

        $this->logActivity('Schedule Template Created', "Employee: {$employeeId}, Schedule ID: {$mainScheduleId}");

        // 2. Assign this main schedule to the employee.
        $effectiveDate = date('Y-m-d');
        $stmt = $this->db->prepare("
            INSERT INTO employee_schedules (employee_id, schedule_id, effective_date, is_active) 
            VALUES (?, ?, ?, 1)
        ");
        $stmt->bind_param('iis', $employeeId, $mainScheduleId, $effectiveDate);
        $stmt->execute();

        // 3. Iterate through each schedule block from the UI and add periods/assignments.
        // IMPORTANT: Use 0-6 format (Monday=0, Sunday=6) to match Python's datetime.weekday()
        $dayMappings = [
            'Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2, 'Thursday' => 3,
            'Friday' => 4, 'Saturday' => 5, 'Sunday' => 6
        ];

        foreach ($scheduleData as $scheduleBlock) {
            $isFacultySchedule = ($scheduleBlock['class'] !== 'N/A' && $scheduleBlock['subject'] !== 'GENERAL');

            foreach ($scheduleBlock['days'] as $dayName) {
                $dayOfWeek = $dayMappings[$dayName] ?? null;
                if ($dayOfWeek === null) continue;

                // Create a schedule period for this time block on this day
                $periodName = $isFacultySchedule ? ($scheduleBlock['subject'] . " - " . $scheduleBlock['class']) : 'Work Shift';
                $stmt = $this->db->prepare("
                    INSERT INTO schedule_periods (schedule_id, day_of_week, period_name, start_time, end_time) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('iisss', $mainScheduleId, $dayOfWeek, $periodName, $scheduleBlock['startTime'], $scheduleBlock['endTime']);
                $stmt->execute();
                $periodId = $this->db->insert_id;

                if (!$periodId) {
                    $this->logError('Schedule Processing', "Failed to create schedule_period for day {$dayName}");
                    continue; // Skip to next day
                }

                // If it's a faculty schedule, create a specific assignment
                if ($isFacultySchedule) {
                    $room_num = $scheduleBlock['room_num'] ?? 'TBD';
                    $designate_class = $scheduleBlock['class'] ?? '';
                    $subject_code = $scheduleBlock['subject'] ?? '';

                    $stmt = $this->db->prepare("
                        INSERT INTO employee_assignments (employee_id, schedule_period_id, subject_code, designate_class, room_num, is_active) 
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->bind_param('iisss', $employeeId, $periodId, $subject_code, $designate_class, $room_num);
                    $stmt->execute();
                }
            }
        }
    }

    private function sendErrorResponse($message, $code = 400, $errors = []) {
        // Log JSON response to console instead of displaying to user
        $jsonResponse = json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ]);
        error_log("Employee Addition Error (HTTP $code): " . $jsonResponse);
        
        // Store error in session to display at top of page
        $_SESSION['error_message'] = $message;
        $_SESSION['error_details'] = $errors;
        
        // Redirect back to the form page without URL parameters
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../index.php'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $processor = new EmployeeProcessor($conn);
    $processor->handleRequest();
} else {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}