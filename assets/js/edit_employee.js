/**
 * add_employee.js
 *
 * Modular JavaScript for Employee Management UI
 * Includes: CustomDropdown, EmployeeIDValidator, FacultyFieldToggle
 *
 * Author: [Your Name]
 * Date: 2025-10-28
 *
 * This file is organized for production-level maintainability and clarity.
 * Each class/function is documented and separated by section.
 */

// =========================
// CustomDropdown Class
// =========================

/**
 * CustomDropdown
 * Creates a custom dropdown UI for input fields, supporting filtering, keyboard navigation, and option selection.
 * Usage: new CustomDropdown(inputElement, optionsArray)
 */
class CustomDropdown {
    /**
     * @param {HTMLElement} inputElement - The input element to attach the dropdown to.
     * @param {Array<string>} options - The list of options for the dropdown.
     */
    constructor(inputElement, options = []) {
        this.input = inputElement;
        this.options = options;
        this.filteredOptions = [...options];
        this.selectedIndex = -1;
        this.isOpen = false;
        this.init();
    }

    /**
     * Initialize dropdown structure and events.
     */
    init() {
        this.createDropdownStructure();
        this.bindEvents();
        // Hide original datalist if present
        const datalist = document.getElementById(this.input.getAttribute('list'));
        if (datalist) {
            datalist.style.display = 'none';
        }
    }

    /**
     * Create dropdown HTML structure.
     */
    createDropdownStructure() {
        const wrapper = document.createElement('div');
        wrapper.className = 'custom-dropdown';
        this.input.parentNode.insertBefore(wrapper, this.input);
        wrapper.appendChild(this.input);
        // Dropdown arrow
        const arrow = document.createElement('div');
        arrow.className = 'dropdown-arrow';
        wrapper.appendChild(arrow);
        // Dropdown list
        this.dropdownList = document.createElement('div');
        this.dropdownList.className = 'dropdown-list';
        wrapper.appendChild(this.dropdownList);
        this.wrapper = wrapper;
        this.arrow = arrow;
        this.updateDropdownList();
    }

    /**
     * Bind input and dropdown events.
     */
    bindEvents() {
        this.input.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleDropdown();
        });
        this.input.addEventListener('input', (e) => {
            this.filterOptions(e.target.value);
            this.openDropdown();
        });
        this.input.addEventListener('keydown', (e) => {
            this.handleKeyNavigation(e);
        });
        this.input.addEventListener('blur', (e) => {
            setTimeout(() => {
                if (!this.wrapper.contains(document.activeElement)) {
                    this.closeDropdown();
                }
            }, 150);
        });
        document.addEventListener('click', (e) => {
            if (!this.wrapper.contains(e.target)) {
                this.closeDropdown();
            }
        });
    }

    /**
     * Filter dropdown options by search term.
     * @param {string} searchTerm
     */
    filterOptions(searchTerm) {
        const term = searchTerm.toLowerCase().trim();
        this.filteredOptions = term === ''
            ? [...this.options]
            : this.options.filter(option => option.toLowerCase().includes(term));
        this.selectedIndex = -1;
        this.updateDropdownList();
    }

    /**
     * Update dropdown list UI.
     */
    updateDropdownList() {
        this.dropdownList.innerHTML = '';
        if (this.filteredOptions.length === 0) {
            const noResults = document.createElement('div');
            noResults.className = 'dropdown-item no-results';
            noResults.textContent = 'No options found';
            this.dropdownList.appendChild(noResults);
            return;
        }
        this.filteredOptions.forEach((option, index) => {
            const item = document.createElement('div');
            item.className = 'dropdown-item';
            item.textContent = option;
            item.dataset.index = index;
            item.addEventListener('click', () => {
                this.selectOption(option);
            });
            this.dropdownList.appendChild(item);
        });
    }

    /**
     * Select an option from the dropdown.
     * @param {string} option
     */
    selectOption(option) {
        const uppercaseFields = ['designate_class', 'designate_subject', 'room-number'];
        if (uppercaseFields.includes(this.input.id)) {
            this.input.value = option.toUpperCase();
        } else {
            this.input.value = option;
        }
        this.input.dispatchEvent(new Event('change', { bubbles: true }));
        this.closeDropdown();
    }

    /**
     * Open the dropdown list.
     */
    openDropdown() {
        this.isOpen = true;
        this.wrapper.classList.add('open');
        this.updateDropdownList();
    }

    /**
     * Close the dropdown list.
     */
    closeDropdown() {
        this.isOpen = false;
        this.wrapper.classList.remove('open');
        this.selectedIndex = -1;
    }

    /**
     * Toggle dropdown open/close.
     */
    toggleDropdown() {
        this.isOpen ? this.closeDropdown() : this.openDropdown();
    }

    /**
     * Handle keyboard navigation in dropdown.
     * @param {KeyboardEvent} e
     */
    handleKeyNavigation(e) {
        if (!this.isOpen) return;
        const items = this.dropdownList.querySelectorAll('.dropdown-item:not(.no-results)');
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.selectedIndex = Math.min(this.selectedIndex + 1, items.length - 1);
                this.highlightOption();
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
                this.highlightOption();
                break;
            case 'Enter':
                e.preventDefault();
                if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
                    const option = items[this.selectedIndex].textContent;
                    this.selectOption(option);
                }
                break;
            case 'Escape':
                this.closeDropdown();
                break;
        }
    }

    /**
     * Highlight the currently selected option.
     */
    highlightOption() {
        const items = this.dropdownList.querySelectorAll('.dropdown-item:not(.no-results)');
        items.forEach(item => item.classList.remove('selected'));
        if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
            items[this.selectedIndex].classList.add('selected');
            items[this.selectedIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    /**
     * Add a new option to the dropdown.
     * @param {string} option
     */
    addOption(option) {
        if (!this.options.includes(option)) {
            this.options.push(option);
            this.filteredOptions = [...this.options];
            this.updateDropdownList();
        }
    }

    /**
     * Update dropdown options.
     * @param {Array<string>} newOptions
     */
    updateOptions(newOptions) {
        this.options = [...newOptions];
        this.filteredOptions = [...newOptions];
        this.updateDropdownList();
    }
}

// =========================
// EmployeeIDValidator Class
// =========================

/**
 * EmployeeIDValidator
 * Validates employee ID input asynchronously, shows status messages, and updates input styling.
 * Usage: new EmployeeIDValidator()
 */
class EmployeeIDValidator {
    constructor() {
        this.validationTimeout = null;
        this.lastValidatedId = '';
        this.isValidating = false;
        this.validationDelay = 800; // ms
        this.init();
    }

    /**
     * Initialize validator UI and events.
     */
    init() {
        const employeeIdInput = document.getElementById('employee_id');
        if (!employeeIdInput) return;
        this.createValidationUI(employeeIdInput);
        employeeIdInput.addEventListener('input', (e) => this.handleInput(e));
        employeeIdInput.addEventListener('blur', (e) => this.handleBlur(e));
        employeeIdInput.addEventListener('focus', (e) => this.handleFocus(e));
    }

    /**
     * Create validation message container after input.
     * @param {HTMLElement} input
     */
    createValidationUI(input) {
        const validationMsg = document.createElement('div');
        validationMsg.id = 'employee-id-validation';
        validationMsg.className = 'validation-message';
        validationMsg.style.cssText = `
            margin-top: 5px;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 500;
            display: none;
            transition: all 0.3s ease;
        `;
        input.parentNode.insertBefore(validationMsg, input.nextSibling);
    }

    /**
     * Handle input event for validation.
     * @param {Event} e
     */
    handleInput(e) {
        const value = e.target.value.trim();
        if (this.validationTimeout) clearTimeout(this.validationTimeout);
        if (!value) {
            this.resetValidationUI(e.target);
            return;
        }
        this.showValidationMessage('checking', 'Checking availability...', e.target);
        this.validationTimeout = setTimeout(() => {
            this.validateEmployeeId(value, e.target);
        }, this.validationDelay);
    }

    /**
     * Handle blur event for validation.
     * @param {Event} e
     */
    handleBlur(e) {
        const value = e.target.value.trim();
        if (value && value !== this.lastValidatedId) {
            if (this.validationTimeout) clearTimeout(this.validationTimeout);
            this.validateEmployeeId(value, e.target);
        }
    }

    /**
     * Handle focus event to clear messages.
     * @param {Event} e
     */
    handleFocus(e) {
        const msgContainer = document.getElementById('employee-id-validation');
        if (msgContainer && msgContainer.style.display === 'none') {
            msgContainer.style.display = 'none';
        }
    }

    /**
     * Validate employee ID via AJAX.
     * @param {string} employeeId
     * @param {HTMLElement} inputElement
     */
    async validateEmployeeId(employeeId, inputElement) {
        if (this.isValidating || employeeId === this.lastValidatedId) return;
        this.isValidating = true;
        try {
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
            if (!csrfToken) throw new Error('Security token not found on page');
            const requestData = { employee_id: employeeId, csrf_token: csrfToken };
            const response = await fetch('processes/validate_employee_id.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(requestData)
            });
            const responseText = await response.text();
            if (!response.ok) {
                let errorMessage = `HTTP ${response.status}: ${response.statusText}`;
                try {
                    const errorData = JSON.parse(responseText);
                    if (errorData.message) errorMessage = errorData.message;
                } catch (e) {}
                throw new Error(errorMessage);
            }
            const result = JSON.parse(responseText);
            if (result.success && result.available) {
                this.showValidationMessage('success', '✓ Employee ID is available', inputElement);
                this.setInputValidationState(inputElement, 'valid');
            } else if (!result.available) {
                this.showValidationMessage('error', '✗ Employee ID already exists', inputElement);
                this.setInputValidationState(inputElement, 'invalid');
            } else {
                this.showValidationMessage('error', result.message || 'Validation failed', inputElement);
                this.setInputValidationState(inputElement, 'invalid');
            }
            this.lastValidatedId = employeeId;
        } catch (error) {
            if (error.message.includes('429')) {
                this.showValidationMessage('warning', '⚠ Too many requests. Please wait a moment.', inputElement);
            } else if (error.message.includes('403')) {
                this.showValidationMessage('error', '✗ Security token expired. Refreshing page...', inputElement);
                setTimeout(() => { window.location.reload(); }, 2000);
            } else {
                this.showValidationMessage('warning', '⚠ Unable to verify ID availability. Please try again.', inputElement);
            }
            this.setInputValidationState(inputElement, 'warning');
        } finally {
            this.isValidating = false;
        }
    }

    /**
     * Show validation message below input.
     * @param {'success'|'error'|'warning'|'checking'} type
     * @param {string} message
     * @param {HTMLElement} inputElement
     */
    showValidationMessage(type, message, inputElement) {
        const msgContainer = document.getElementById('employee-id-validation');
        if (!msgContainer) return;
        msgContainer.textContent = message;
        msgContainer.style.display = 'block';
        switch (type) {
            case 'success':
                msgContainer.style.cssText += 'background: #d4edda; color: #155724; border-left: 4px solid #28a745;';
                break;
            case 'error':
                msgContainer.style.cssText += 'background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545;';
                break;
            case 'warning':
                msgContainer.style.cssText += 'background: #fff3cd; color: #856404; border-left: 4px solid #ffc107;';
                break;
            case 'checking':
                msgContainer.style.cssText += 'background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8;';
                break;
        }
    }

    /**
     * Set input border and class based on validation state.
     * @param {HTMLElement} inputElement
     * @param {'valid'|'invalid'|'warning'|'default'} state
     */
    setInputValidationState(inputElement, state) {
        inputElement.classList.remove('input-valid', 'input-invalid', 'input-warning');
        switch (state) {
            case 'valid':
                inputElement.classList.add('input-valid');
                inputElement.style.borderColor = '#28a745';
                break;
            case 'invalid':
                inputElement.classList.add('input-invalid');
                inputElement.style.borderColor = '#dc3545';
                break;
            case 'warning':
                inputElement.classList.add('input-warning');
                inputElement.style.borderColor = '#ffc107';
                break;
            default:
                inputElement.style.borderColor = '';
        }
    }

    /**
     * Reset validation UI to default.
     * @param {HTMLElement} inputElement
     */
    resetValidationUI(inputElement) {
        const msgContainer = document.getElementById('employee-id-validation');
        if (msgContainer) msgContainer.style.display = 'none';
        this.setInputValidationState(inputElement, 'default');
        this.lastValidatedId = '';
    }
}

// =========================
// Faculty Field Toggle System
// =========================

/**
 * Initializes faculty field toggle logic for role changes.
 * Disables/enables class, subject, and room fields based on role selection.
 * Handles schedule clearing and confirmation dialogs.
 */
function initializeFacultyFieldToggle() {
    const rolesInput = document.getElementById('roles');
    const facultyFields = ['designate_class', 'designate_subject', 'room-number'];
    let previousRole = rolesInput ? rolesInput.value.trim() : '';
    if (!rolesInput) return;

    // Analyze existing schedules for faculty/non-faculty types
    function analyzeExistingSchedules() {
        const schedules = window.editAddedSchedules || [];
        if (schedules.length === 0) {
            return { hasSchedules: false, facultySchedules: 0, nonFacultySchedules: 0 };
        }
        let facultySchedules = 0;
        let nonFacultySchedules = 0;
        schedules.forEach(schedule => {
            const isFacultySchedule = schedule.class !== 'N/A' && schedule.subject !== 'GENERAL' && schedule.room_num !== 'TBD';
            if (isFacultySchedule) facultySchedules++;
            else nonFacultySchedules++;
        });
        return {
            hasSchedules: true,
            facultySchedules,
            nonFacultySchedules,
            total: schedules.length
        };
    }

    // Handle role change with existing schedules
    function handleRoleChangeWithSchedules(newRole, previousRole) {
        const scheduleAnalysis = analyzeExistingSchedules();
        if (!scheduleAnalysis.hasSchedules) return true;
        const newIsFaculty = newRole === 'Faculty_Member';
        const previousIsFaculty = previousRole === 'Faculty_Member';
        if (newIsFaculty === previousIsFaculty) return true;
        let confirmationMessage = '';
        let scheduleTypeInfo = '';
        if (scheduleAnalysis.facultySchedules > 0 && scheduleAnalysis.nonFacultySchedules > 0) {
            scheduleTypeInfo = `${scheduleAnalysis.facultySchedules} faculty schedule(s) and ${scheduleAnalysis.nonFacultySchedules} non-faculty schedule(s)`;
        } else if (scheduleAnalysis.facultySchedules > 0) {
            scheduleTypeInfo = `${scheduleAnalysis.facultySchedules} faculty schedule(s)`;
        } else {
            scheduleTypeInfo = `${scheduleAnalysis.nonFacultySchedules} non-faculty schedule(s)`;
        }
        if (newIsFaculty) {
            confirmationMessage = `⚠️ Role Change Detected\n\n` +
                `You are changing from "${previousRole}" to "Faculty_Member".\n\n` +
                `You currently have ${scheduleTypeInfo} created.\n\n` +
                `Changing to Faculty Member will:\n` +
                `• Enable class, subject, and room fields\n` +
                `• Allow creation of detailed faculty schedules\n` +
                `• Clear existing schedules to prevent mixing different schedule types\n\n` +
                `Do you want to proceed?\n` +
                `(All existing schedules will be cleared)`;
        } else {
            confirmationMessage = `⚠️ Role Change Detected\n\n` +
                `You are changing from "Faculty_Member" to "${newRole}".\n\n` +
                `You currently have ${scheduleTypeInfo} created.\n\n` +
                `Changing from Faculty Member will:\n` +
                `• Disable class, subject, and room fields\n` +
                `• Only allow basic work schedules\n` +
                `• Clear existing schedules to prevent mixing different schedule types\n\n` +
                `Do you want to proceed?\n` +
                `(All existing schedules will be cleared)`;
        }
        const userConfirmed = confirm(confirmationMessage);
        if (userConfirmed) {
            if (typeof window.clearAllSchedulesQuietly === 'function') {
                window.clearAllSchedulesQuietly();
            } else if (typeof window.addedSchedules !== 'undefined') {
                window.addedSchedules = [];
                if (typeof window.renderSchedules === 'function') {
                    window.renderSchedules();
                }
            }
            setTimeout(() => {
                alert(`✅ Role changed successfully!\n\nAll schedules have been cleared.\nYou can now create new schedules for the "${newRole}" role.`);
            }, 100);
            return true;
        } else {
            return false;
        }
    }

    // Toggle faculty-specific fields based on role
    function toggleFacultyFields(skipScheduleCheck = false) {
        const currentRole = rolesInput.value.trim();
        if (!skipScheduleCheck && currentRole !== previousRole) {
            const shouldProceed = handleRoleChangeWithSchedules(currentRole, previousRole);
            if (!shouldProceed) {
                rolesInput.value = previousRole;
                return;
            }
        }
        previousRole = currentRole;
        const isFaculty = currentRole === 'Faculty_Member';
        const roleGroup = rolesInput.closest('.form-group');
        const facultyFieldsGroup = document.getElementById('faculty-fields');
        if (roleGroup) {
            roleGroup.classList.add('role-changing');
            setTimeout(() => roleGroup.classList.remove('role-changing'), 1000);
        }
        if (facultyFieldsGroup) {
            facultyFieldsGroup.style.transform = 'scale(1.02)';
            setTimeout(() => {
                facultyFieldsGroup.style.transform = 'scale(1)';
            }, 300);
        }
        facultyFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            const fieldGroup = field?.closest('.form-group');
            if (field) {
                if (isFaculty) {
                    field.disabled = false;
                    field.placeholder = field.id === 'designate_class' ? 'Select or type class name' :
                        field.id === 'designate_subject' ? 'Select or type subject' :
                        'Select or type room number';
                    field.style.opacity = '1';
                    field.style.cursor = 'text';
                    const customDropdown = field.closest('.custom-dropdown');
                    if (customDropdown) {
                        customDropdown.style.opacity = '1';
                        customDropdown.style.pointerEvents = 'auto';
                    }
                } else {
                    field.disabled = true;
                    field.value = '';
                    field.placeholder = 'Available for Faculty Members only';
                    field.style.opacity = '0.6';
                    field.style.cursor = 'not-allowed';
                    const customDropdown = field.closest('.custom-dropdown');
                    if (customDropdown) {
                        customDropdown.style.opacity = '0.6';
                        customDropdown.style.pointerEvents = 'none';
                    }
                }
                if (fieldGroup) {
                    fieldGroup.style.opacity = isFaculty ? '1' : '0.6';
                }
            }
        });
        // Log for debugging
        console.log(`Faculty fields ${isFaculty ? 'enabled' : 'disabled'} for role: ${currentRole}`);
    }

    // Initial toggle (skip schedule check on init)
    toggleFacultyFields(true);
    // Listen for role changes
    rolesInput.addEventListener('input', () => toggleFacultyFields(false));
    rolesInput.addEventListener('change', () => toggleFacultyFields(false));
    rolesInput.addEventListener('blur', () => toggleFacultyFields(false));
}

// =========================
// DOMContentLoaded Initialization
// =========================

/**
 * Initializes all UI components when DOM is ready.
 * - CustomDropdowns for roles, department, class, subject, room
 * - EmployeeIDValidator
 * - FacultyFieldToggle
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Employee ID Validator
    new EmployeeIDValidator();

    // Get input elements
    const rolesInput = document.getElementById('roles');
    const departmentInput = document.getElementById('department');
    const classInput = document.getElementById('designate_class');
    const subjectInput = document.getElementById('designate_subject');
    const roomInput = document.getElementById('room-number');

    // Initialize faculty field management
    initializeFacultyFieldToggle();

    // CustomDropdowns for each field
    if (rolesInput) {
        // Get roles options from PHP
        const rolesOptions = [
            'Administrator',
            'Faculty_Member',
            'Non-Teaching_Personnel'
            // Additional roles injected by PHP
            // ...existing code...
        ];
        new CustomDropdown(rolesInput, rolesOptions);
    }
    if (departmentInput) {
        const departmentOptions = [
            // ...existing code...
        ];
        new CustomDropdown(departmentInput, departmentOptions);
    }
    if (classInput) {
        const classOptions = [
            // ...existing code...
        ];
        const classDropdown = new CustomDropdown(classInput, classOptions);
        classInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }
    if (subjectInput) {
        const subjectOptions = [
            // ...existing code...
        ];
        const subjectDropdown = new CustomDropdown(subjectInput, subjectOptions);
        subjectInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }
    if (roomInput) {
        const roomOptions = [
            // ...existing code...
        ];
        const roomDropdown = new CustomDropdown(roomInput, roomOptions);
        roomInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }
});
// Debug: Log when scripts are loaded
console.log('✓ All scripts loaded at:', new Date().toLocaleTimeString());
console.log('✓ FaceDetection class available:', typeof FaceDetection !== 'undefined');
console.log('✓ CameraController class available:', typeof CameraController !== 'undefined');
console.log('✓ FaceRegistrationApp class available:', typeof FaceRegistrationApp !== 'undefined');

// Schedule Day Toggle Functionality (Edit Modal)
let editSelectedDays = [];
let editAddedSchedules = []; // Array to store all added schedules in edit modal
let editCurrentlyEditingIndex = null; // To track which schedule is being edited

// Expose variables and functions to global scope for role change detection
window.editAddedSchedules = editAddedSchedules;
window.editSelectedDays = editSelectedDays;

// Initialize calendar on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeCalendar();
    setupRoleInput();
    setupDepartmentInput();
    
    // Add form submission handler to serialize schedule data for update_employee
    const updateForm = document.querySelector('form[action="processes/update_employee.php"]');
    if (updateForm) {
        updateForm.addEventListener('submit', function(e) {
            // Serialize schedule data before submission
            const scheduleData = JSON.stringify(editAddedSchedules);
            document.getElementById('schedule_data').value = scheduleData;
            console.log('Form submission - Schedule data:', scheduleData);
            console.log('Total schedules to save:', editAddedSchedules.length);
        });
    }
    // --- Load existing schedules from PHP into the JS array ---
    if (window.existingSchedules && Array.isArray(window.existingSchedules) && window.existingSchedules.length > 0) {
        console.log('Loading existing schedules from PHP:', window.existingSchedules);
        editAddedSchedules = window.existingSchedules.map(schedule => {
            // Assign a color to each schedule block
            schedule.color = getRandomEditScheduleColor();
            return schedule;
        });
        window.editAddedSchedules = editAddedSchedules; // Update global reference
        renderSchedules(); // Render the loaded schedules on the calendar
        console.log('Schedules loaded and rendered.');
    }
});

// Setup role input functionality
function setupRoleInput() {
    const roleInput = document.getElementById('roles');
    if (!roleInput) return;
    
    // Add styling for better UX
    roleInput.addEventListener('focus', function() {
        this.style.borderColor = '#007bff';
    });
    
    roleInput.addEventListener('blur', function() {
        this.style.borderColor = '';
        // Trim whitespace from the input
        this.value = this.value.trim();
    });
    
    // Convert to proper case when user types
    roleInput.addEventListener('input', function() {
        // Don't auto-format while user is typing, but provide visual feedback
        const value = this.value.trim();
        if (value.length > 0) {
            // Optional: You can add validation feedback here
            console.log('Role input:', value);
        }
    });
    
    // Format the role properly on blur
    roleInput.addEventListener('blur', function() {
        let value = this.value.trim();
        if (value) {
            // Convert to proper format (first letter caps, replace spaces with underscores for consistency)
            value = value.split(' ').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
            ).join('_');
            
            this.value = value;
        }
    });
}



// Setup department input functionality
function setupDepartmentInput() {
    const deptInput = document.getElementById('department');
    if (!deptInput) return;
    
    // Add styling for better UX
    deptInput.addEventListener('focus', function() {
        this.style.borderColor = '#007bff';
    });
    
    deptInput.addEventListener('blur', function() {
        this.style.borderColor = '';
        // Trim whitespace and format properly
        let value = this.value.trim();
        if (value) {
            // Convert to proper format (title case)
            value = value.split(' ').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
            ).join(' ');
            
            this.value = value;
        }
    });
    
    // Provide visual feedback while typing
    deptInput.addEventListener('input', function() {
        const value = this.value.trim();
        if (value.length > 0) {
            console.log('Department input:', value);
        }
    });
}



// Predefined color palette for schedule blocks (Edit Modal)
const editScheduleColors = [
    '#4a7c59', // Dark green
    '#8b4a6b', // Purple/magenta
    '#b85450', // Red
    '#5b9bd5', // Blue
    '#ffc000', // Yellow/gold
    '#c55a11', // Orange
    '#7030a0', // Purple
    '#0070c0', // Dark blue
    '#00b050', // Bright green
    '#ff6b6b', // Coral red
];

// Function to get random color for schedule blocks (Edit Modal)
function getRandomEditScheduleColor() {
    return editScheduleColors[Math.floor(Math.random() * editScheduleColors.length)];
}

function toggleDay(button) {
    console.log('toggleDay called for button:', button);
    const day = button.getAttribute('data-day');
    console.log('Day:', day);
    
    // Toggle the active class
    button.classList.toggle('active');
    console.log('Button classes after toggle:', button.className);
    
    // Update selected days array
    if (button.classList.contains('active')) {
        if (!editSelectedDays.includes(day)) {
            editSelectedDays.push(day);
        }
        console.log('Added day:', day);
    } else {
        editSelectedDays = editSelectedDays.filter(d => d !== day);
        console.log('Removed day:', day);
    }
    
    // Update hidden input
    document.getElementById('work_days').value = JSON.stringify(editSelectedDays);
    console.log('Selected work days:', editSelectedDays);
    console.log('Button background:', window.getComputedStyle(button).backgroundColor);
}

// Test function to manually add active class
function testDimming() {
    const firstButton = document.querySelector('.day-btn');
    firstButton.classList.add('active');
    console.log('Test: Added active class to first button');
    console.log('Test: Button classes:', firstButton.className);
    console.log('Test: Computed background:', window.getComputedStyle(firstButton).backgroundColor);
}

// Password toggle functionality
function togglePassword(inputId) {
    const passwordInput = document.getElementById(inputId);
    const toggleButton = passwordInput.nextElementSibling;
    const eyeIcon = toggleButton.querySelector('.eye-icon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.textContent = '🙈';
        toggleButton.title = 'Hide Password';
    } else {
        passwordInput.type = 'password';
        eyeIcon.textContent = '👁️';
        toggleButton.title = 'Show Password';
    }
}

// Calendar initialization
function initializeCalendar() {
    const calendar = document.querySelector('.schedule-calendar');
    const timeSlots = generateTimeSlots('07:00', '24:00', 30); // 7AM to 12AM (midnight), 30-minute intervals
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    
    // Clear existing grid content (keep headers)
    const existingCells = calendar.querySelectorAll('.time-slot, .calendar-cell');
    existingCells.forEach(cell => cell.remove());
    
    // Set up grid rows (header + time slots)
    calendar.style.gridTemplateRows = `40px repeat(${timeSlots.length}, 40px)`;
    
    // Create time slots and calendar cells
    timeSlots.forEach((timeSlot, timeIndex) => {
        // Time slot label
        const timeLabel = document.createElement('div');
        timeLabel.className = 'time-slot';
        timeLabel.textContent = formatTime(timeSlot);
        timeLabel.style.gridColumn = '1';
        timeLabel.style.gridRow = `${timeIndex + 2}`;
        calendar.appendChild(timeLabel);
        
        // Calendar cells for each day
        days.forEach((day, dayIndex) => {
            const cell = document.createElement('div');
            cell.className = 'calendar-cell';
            cell.dataset.day = day;
            cell.dataset.timeSlot = timeSlot;
            cell.dataset.timeIndex = timeIndex;
            cell.style.gridColumn = `${dayIndex + 2}`;
            cell.style.gridRow = `${timeIndex + 2}`;
            calendar.appendChild(cell);
        });
    });
    
    // Render existing schedules
    renderSchedules();
}

function generateTimeSlots(startTime, endTime, intervalMinutes) {
    const slots = [];
    const start = parseTime(startTime);
    const end = parseTime(endTime);
    
    let current = start;
    while (current < end) {
        slots.push(formatTimeSlot(current));
        current += intervalMinutes;
    }
    
    return slots;
}

function parseTime(timeString) {
    const [hours, minutes] = timeString.split(':').map(Number);
    return hours * 60 + minutes;
}

function formatTimeSlot(minutes) {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
}

function formatTime(timeSlot) {
    const [hours, minutes] = timeSlot.split(':').map(Number);
    const period = hours >= 12 ? 'PM' : 'AM';
    const displayHours = hours > 12 ? hours - 12 : (hours === 0 ? 12 : hours);
    return `${displayHours}:${minutes.toString().padStart(2, '0')}${period}`;
}

function renderSchedules() {
    // Clear existing schedule blocks
    document.querySelectorAll('.schedule-block').forEach(block => block.remove());
    
    // Re-render all schedules with updated indices
    editAddedSchedules.forEach((schedule, index) => {
        renderScheduleBlock(schedule, index);
    });
}

function renderScheduleBlock(schedule, scheduleIndex) {
    const startTimeMinutes = parseTime(schedule.startTime);
    const endTimeMinutes = parseTime(schedule.endTime);
    const baseTimeMinutes = 420; // 7:00 AM in minutes
    const slotDuration = 30; // 30-minute slots
    const slotHeight = 40; // 40px per slot
    
    // Calculate slot positions
    const startSlotIndex = Math.floor((startTimeMinutes - baseTimeMinutes) / slotDuration);
    const endSlotIndex = Math.ceil((endTimeMinutes - baseTimeMinutes) / slotDuration);
    const slotsSpanned = endSlotIndex - startSlotIndex;
    
    schedule.days.forEach(day => {
        const dayIndex = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].indexOf(day);
        if (startSlotIndex >= 0 && endSlotIndex <= 34) {
            const targetCell = document.querySelector(`[data-day="${day}"][data-time-index="${startSlotIndex}"]`);
            if (targetCell) {
                const scheduleBlock = document.createElement('div');
                const isFacultySchedule = schedule.class !== 'N/A' && schedule.subject !== 'GENERAL' && schedule.room_num !== 'TBD';
                scheduleBlock.className = isFacultySchedule ? 'schedule-block faculty-schedule' : 'schedule-block non-faculty-schedule';
                scheduleBlock.dataset.scheduleId = scheduleIndex;
                scheduleBlock.dataset.day = day;
                scheduleBlock.dataset.startTime = schedule.startTime;
                scheduleBlock.dataset.endTime = schedule.endTime;
                scheduleBlock.style.background = schedule.color || getRandomScheduleColor();
                const exactHeight = slotsSpanned * slotHeight;
                scheduleBlock.style.height = `${exactHeight}px`;
                let scheduleContent = '';
                if (isFacultySchedule) {
                    scheduleContent = `
                        <div class="class-subject">${schedule.class}<br>${schedule.subject}</div>
                        <div class="room-info">Room: ${schedule.room_num}</div>
                        <div class="time-range">${formatTime(schedule.startTime)} - ${formatTime(schedule.endTime)}</div>
                    `;
                } else {
                    scheduleContent = `
                        <div class="time-range-only">${formatTime(schedule.startTime)} - ${formatTime(schedule.endTime)}</div>
                        <div class="schedule-type">Work Schedule</div>
                    `;
                }
                scheduleBlock.innerHTML = `
                    <div class="schedule-delete-btn" onclick="deleteSchedule(${scheduleIndex}, '${day}')">×</div>
                    <div class="schedule-info">
                        ${scheduleContent}
                    </div>
                `;
                // --- Make block editable ---
                scheduleBlock.addEventListener('click', function(e) {
                    e.stopPropagation();
                    // Fill form fields
                    currentlyEditingIndex = scheduleIndex;
                    document.getElementById('shift_start').value = schedule.startTime;
                    document.getElementById('shift_end').value = schedule.endTime;
                    document.getElementById('designate_class').value = schedule.class || '';
                    document.getElementById('designate_subject').value = schedule.subject || '';
                    document.getElementById('room-number').value = schedule.room_num || '';
                    // Set day buttons
                    selectedDays = [...schedule.days];
                    window.selectedDays = selectedDays;
                    document.querySelectorAll('.day-btn').forEach(btn => {
                        const btnDay = btn.getAttribute('data-day');
                        if (selectedDays.includes(btnDay)) {
                            btn.classList.add('active');
                        } else {
                            btn.classList.remove('active');
                        }
                    });
                    document.getElementById('work_days').value = JSON.stringify(selectedDays);
                    // Optionally scroll to form or highlight
                    document.getElementById('edit-schedule-btn').disabled = false;
                    document.querySelector('.schedule-section').scrollIntoView({ behavior: 'smooth' });
                    document.getElementById('shift_start').focus();
                });
                targetCell.appendChild(scheduleBlock);
            }
        }
    });
}

function checkConsecutiveSchedule(day, timeMinutes, direction) {
    return addedSchedules.some(schedule => {
        if (!schedule.days.includes(day)) return false;
        
        const scheduleStart = parseTime(schedule.startTime);
        const scheduleEnd = parseTime(schedule.endTime);
        
        if (direction === 'before') {
            return Math.abs(scheduleEnd - timeMinutes) <= 1; // Within 1 minute tolerance
        } else if (direction === 'after') {
            return Math.abs(scheduleStart - timeMinutes) <= 1; // Within 1 minute tolerance
        }
        
        return false;
    });
}

// Add Schedule functionality
function addSchedule() {
    // Validate that at least one day is selected
    if (editSelectedDays.length === 0) {
        alert('Please select at least one working day first!');
        return;
    }
    
    // Get shift times
    const shiftStart = document.getElementById('shift_start').value;
    const shiftEnd = document.getElementById('shift_end').value;
    
    if (!shiftStart || !shiftEnd) {
        alert('Please select both start and end times!');
        return;
    }
    
    // Validate time order
    if (shiftStart >= shiftEnd) {
        alert('Start time must be before end time!');
        return;
    }
    
    // Get class, subject, and room
    const designateClass = document.getElementById('designate_class').value;
    const designateSubject = document.getElementById('designate_subject').value;
    const roomNumber = document.getElementById('room-number').value;
    
    // Check if user is faculty member
    const rolesInput = document.getElementById('roles');
    const currentRole = rolesInput ? rolesInput.value.trim() : '';
    const isFaculty = currentRole === 'Faculty_Member';
    
    if (isFaculty && (!designateClass || !designateSubject || !roomNumber)) {
        alert('Faculty members must enter class, subject, and room number for schedules!');
        return;
    }
    
    // For non-faculty, use default values
    const finalClass = isFaculty ? designateClass : 'N/A';
    const finalSubject = isFaculty ? designateSubject : 'General';
    const finalRoom = isFaculty ? roomNumber : 'TBD';
    
    // Create schedule object
    const scheduleData = {
        days: [...editSelectedDays], // Copy array
        startTime: shiftStart,
        endTime: shiftEnd,
        class: finalClass.toUpperCase(), // Ensure uppercase for consistency
        subject: finalSubject.toUpperCase(), // Ensure uppercase for consistency
        room_num: finalRoom.toUpperCase(), // Ensure uppercase for consistency
        color: getRandomEditScheduleColor() // Assign random color to this schedule
    };
    
    // Check for conflicts
    if (checkScheduleConflict(scheduleData)) {
        const proceed = confirm('This schedule conflicts with an existing schedule. Do you want to add it anyway?');
        if (!proceed) {
            return;
        }
    }
    
    // Add to schedules array
    editAddedSchedules.push(scheduleData);
    window.editAddedSchedules = editAddedSchedules; // Keep global reference in sync
    
    console.log('Schedule created:', scheduleData);
    console.log('All schedules:', editAddedSchedules);
    console.log('Schedule data for backend:', {
        days: scheduleData.days,
        startTime: scheduleData.startTime,
        endTime: scheduleData.endTime,
        class: scheduleData.class,
        subject: scheduleData.subject,
        room_num: scheduleData.room_num
    });
    
    // Re-render calendar
    renderSchedules();
    
    // Show confirmation
    const daysList = editSelectedDays.join(', ');
    const roleDisplay = isFaculty ? 'Faculty Member' : 'Non-Faculty';
    const message = `Schedule Added Successfully!\n\nRole: ${roleDisplay}\nDays: ${daysList}\nTime: ${shiftStart} - ${shiftEnd}\nClass: ${finalClass.toUpperCase()}\nSubject: ${finalSubject.toUpperCase()}\nRoom: ${finalRoom.toUpperCase()}`;
    alert(message);
    
    // Clear the form for next schedule entry
    clearScheduleForm();
}

function editSchedule() {
    if (editCurrentlyEditingIndex === null) {
        alert('Please select a schedule block from the calendar to edit.');
        return;
    }

    // Validate that at least one day is selected
    if (editSelectedDays.length === 0) {
        alert('Please select at least one working day first!');
        return;
    }

    // Get shift times
    const shiftStart = document.getElementById('shift_start').value;
    const shiftEnd = document.getElementById('shift_end').value;

    if (!shiftStart || !shiftEnd) {
        alert('Please select both start and end times!');
        return;
    }

    // Validate time order
    if (shiftStart >= shiftEnd) {
        alert('Start time must be before end time!');
        return;
    }

    // Get class, subject, and room
    const designateClass = document.getElementById('designate_class').value;
    const designateSubject = document.getElementById('designate_subject').value;
    const roomNumber = document.getElementById('room-number').value;

    // Check if user is faculty member
    const rolesInput = document.getElementById('roles');
    const currentRole = rolesInput ? rolesInput.value.trim() : '';
    const isFaculty = currentRole === 'Faculty_Member';

    if (isFaculty && (!designateClass || !designateSubject || !roomNumber)) {
        alert('Faculty members must enter class, subject, and room number for schedules!');
        return;
    }

    // For non-faculty, use default values
    const finalClass = isFaculty ? designateClass : 'N/A';
    const finalSubject = isFaculty ? designateSubject : 'General';
    const finalRoom = isFaculty ? roomNumber : 'TBD';

    // Update the schedule object in the array
    const originalColor = editAddedSchedules[editCurrentlyEditingIndex].color;
    editAddedSchedules[editCurrentlyEditingIndex] = {
        days: [...editSelectedDays], startTime: shiftStart, endTime: shiftEnd,
        class: finalClass.toUpperCase(), subject: finalSubject.toUpperCase(), room_num: finalRoom.toUpperCase(),
        color: originalColor
    };

    renderSchedules();
    alert('Schedule updated successfully!');
    clearScheduleForm();
}

function clearScheduleForm() {
    // Clear selected days
    editSelectedDays = [];
    document.querySelectorAll('.day-btn.active').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Clear time inputs
    document.getElementById('shift_start').value = '';
    document.getElementById('shift_end').value = '';
    
    // Only clear faculty fields if they're enabled (for faculty members)
    const rolesInput = document.getElementById('roles');
    const currentRole = rolesInput ? rolesInput.value.trim() : '';
    const isFaculty = currentRole === 'Faculty_Member';
    
    if (isFaculty) {
        document.getElementById('designate_class').value = '';
        document.getElementById('designate_subject').value = '';
        document.getElementById('room-number').value = '';
    }
    
    // Update hidden input
    document.getElementById('work_days').value = '';
    
    // Reset editing state
    editCurrentlyEditingIndex = null;
    document.getElementById('edit-schedule-btn').disabled = true;

    console.log('Schedule form cleared');
}

function clearAllSchedules() {
    if (editAddedSchedules.length === 0) {
        alert('No schedules to clear!');
        return;
    }
    
    if (confirm(`Are you sure you want to clear all ${editAddedSchedules.length} schedule(s)?`)) {
        editAddedSchedules = [];
        window.editAddedSchedules = editAddedSchedules; // Keep global reference in sync
        renderSchedules();
        console.log('All schedules cleared');
        alert('All schedules have been cleared!');
    }
}

// Quiet version for role change functionality (no confirmation dialogs)
function clearAllSchedulesQuietly() {
    editAddedSchedules = [];
    window.editAddedSchedules = editAddedSchedules; // Keep global reference in sync
    renderSchedules();
    console.log('All schedules cleared quietly due to role change');
}

// Expose functions to global scope
window.clearAllSchedulesQuietly = clearAllSchedulesQuietly;
window.renderSchedules = renderSchedules;

// Helper function to validate schedule conflicts
function checkScheduleConflict(newSchedule) {
    return editAddedSchedules.some(existingSchedule => {
        // Check if schedules overlap on any common day
        const commonDays = newSchedule.days.filter(day => existingSchedule.days.includes(day));
        if (commonDays.length === 0) return false;
        
        const newStart = parseTime(newSchedule.startTime);
        const newEnd = parseTime(newSchedule.endTime);
        const existingStart = parseTime(existingSchedule.startTime);
        const existingEnd = parseTime(existingSchedule.endTime);
        
        // Check for time overlap
        return (newStart < existingEnd && newEnd > existingStart);
    });
}

// Delete individual schedule
function deleteSchedule(scheduleIndex, day) {
    if (confirm('Are you sure you want to delete this schedule?')) {
        const schedule = editAddedSchedules[scheduleIndex];
        
        // If schedule is only on this day, remove it completely
        if (schedule.days.length === 1) {
            editAddedSchedules.splice(scheduleIndex, 1);
        } else {
            // Remove just this day from the schedule
            schedule.days = schedule.days.filter(d => d !== day);
        }
        
        window.editAddedSchedules = editAddedSchedules; // Keep global reference in sync
        renderSchedules();
        console.log('Schedule deleted');
    }
}