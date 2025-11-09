// Schedule data from PHP - convert to format matching index.php
const employeeSchedulesRaw = <?php echo json_encode($schedules); ?>;

console.log('Raw employee schedules from database:', employeeSchedulesRaw);

// Predefined color palette matching index.php
const scheduleColors = [
    '#4a7c59', '#8b4a6b', '#b85450', '#5b9bd5', '#ffc000',
    '#c55a11', '#7030a0', '#0070c0', '#00b050', '#ff6b6b'
];

// Convert database schedules to the format used in index.php
function convertSchedulesToDisplayFormat() {
    // IMPORTANT: day_of_week is 0-6 (Monday=0, Sunday=6) to match Python's datetime.weekday()
    const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const scheduleGroups = {};
    
    employeeSchedulesRaw.forEach(schedule => {
        // Create a unique key for grouping schedules with same time/subject/class
        const key = `${schedule.start_time}-${schedule.end_time}-${schedule.subject_code}-${schedule.designate_class}`;
        
        if (!scheduleGroups[key]) {
            scheduleGroups[key] = {
                startTime: schedule.start_time.substring(0, 5), // Convert HH:MM:SS to HH:MM
                endTime: schedule.end_time.substring(0, 5),
                subject: schedule.subject_code,
                class: schedule.designate_class,
                room_num: schedule.room_num,
                days: [],
                color: scheduleColors[Object.keys(scheduleGroups).length % scheduleColors.length]
            };
        }
        
        // day_of_week is already 0-6, so use directly as array index
        const dayName = dayNames[parseInt(schedule.day_of_week)];
        if (!scheduleGroups[key].days.includes(dayName)) {
            scheduleGroups[key].days.push(dayName);
        }
    });
    
    return Object.values(scheduleGroups);
}

const addedSchedules = convertSchedulesToDisplayFormat();
console.log('Converted schedules for display:', addedSchedules);

// Copy exact functions from add_employee.js
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

function getRandomScheduleColor() {
    return scheduleColors[Math.floor(Math.random() * scheduleColors.length)];
}

function initializeCalendar() {
    const calendar = document.getElementById('employee-schedule-calendar');
    if (!calendar) {
        console.error('Calendar element not found!');
        return;
    }
    
    const timeSlots = generateTimeSlots('07:00', '24:00', 30);
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
    
    console.log('Calendar grid created with', timeSlots.length, 'time slots');
    
    // Render schedules
    renderSchedules();
}

function renderSchedules() {
    // Clear existing schedule blocks
    document.querySelectorAll('.schedule-block').forEach(block => block.remove());
    
    console.log('Rendering', addedSchedules.length, 'schedule(s)');
    
    // Re-render all schedules
    addedSchedules.forEach((schedule, index) => {
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
    
    console.log(`Schedule ${scheduleIndex}: ${schedule.startTime}-${schedule.endTime}, slots ${startSlotIndex}-${endSlotIndex}, span ${slotsSpanned}`);
    
    schedule.days.forEach(day => {
        const dayIndex = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].indexOf(day);
        
        if (startSlotIndex >= 0 && endSlotIndex <= 34) { // Within 7AM-12AM range
            // Find the target cell
            const targetCell = document.querySelector(`[data-day="${day}"][data-time-index="${startSlotIndex}"]`);
            
            if (targetCell) {
                const scheduleBlock = document.createElement('div');
                const isFacultySchedule = schedule.class !== 'N/A' && schedule.subject !== 'GENERAL' && schedule.room_num !== 'TBD';
                scheduleBlock.className = isFacultySchedule ? 'schedule-block faculty-schedule' : 'schedule-block non-faculty-schedule';
                
                // Add unique identifier
                scheduleBlock.dataset.scheduleId = scheduleIndex;
                scheduleBlock.dataset.day = day;
                scheduleBlock.dataset.startTime = schedule.startTime;
                scheduleBlock.dataset.endTime = schedule.endTime;
                
                // Apply the schedule's assigned color
                scheduleBlock.style.background = schedule.color || getRandomScheduleColor();
                
                // Calculate exact height
                const exactHeight = slotsSpanned * slotHeight;
                scheduleBlock.style.height = `${exactHeight}px`;
                
                // Generate content based on available information
                let scheduleContent = '';
                
                if (isFacultySchedule) {
                    // Faculty schedule with full information
                    scheduleContent = `
                        <div class="class-subject">${schedule.class}<br>${schedule.subject}</div>
                        <div class="room-info">Room: ${schedule.room_num}</div>
                        <div class="time-range">${formatTime(schedule.startTime)} - ${formatTime(schedule.endTime)}</div>
                    `;
                } else {
                    // Non-faculty or minimal schedule - only show time
                    scheduleContent = `
                        <div class="time-range-only">${formatTime(schedule.startTime)} - ${formatTime(schedule.endTime)}</div>
                        <div class="schedule-type">Work Schedule</div>
                    `;
                }
                
                scheduleBlock.innerHTML = `
                    <div class="schedule-info">
                        ${scheduleContent}
                    </div>
                `;
                
                targetCell.appendChild(scheduleBlock);
                console.log(`Added schedule block to ${day} at slot ${startSlotIndex}`);
            } else {
                console.warn(`Could not find target cell for ${day} at slot ${startSlotIndex}`);
            }
        } else {
            console.warn(`Schedule time out of range: ${schedule.startTime}-${schedule.endTime}`);
        }
    });
}

// Initialize calendar when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing calendar...');
    initializeCalendar();
});