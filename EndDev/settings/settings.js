   // Sidebar Toggle
const menuBtn = document.getElementById("menu-btn");
const sidebar = document.getElementById("sidebar");
const content = document.getElementById("content");
menuBtn.addEventListener('click', () => {
  if (window.innerWidth < 992) {
    // MOBILE: overlay style
    sidebar.classList.toggle('active');
  } else {
    // DESKTOP: push style
    sidebar.classList.toggle('collapsed');
    content.classList.toggle('shift');
  }
});
//----------------------------------------------------------------------------------------------//

document.getElementById("employeeArchive").addEventListener("click", function() {
    // Redirect to emploarc.php when card is clicked
    window.location.href = "emploarc.php";
  });

//changepass
// Open modal when "Change Password" card is clicked
  document.getElementById("changePassword").addEventListener("click", () => {
    const modal = new bootstrap.Modal(document.getElementById("changePasswordModal"));
    modal.show();
  });

  // Handle form submission
  document.getElementById("changePasswordForm").addEventListener("submit", function(e) {
    e.preventDefault(); // Prevent reload

    // Optional: Add password validation here before saving

    // Close modal
    const modalEl = document.getElementById("changePasswordModal");
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    modalInstance.hide();

    // ✅ Show success popup in the middle
    showPopupMessage("Your password has been updated");

    // Redirect back to settings.php after 2 seconds
    setTimeout(() => {
      window.location.href = "settings.php";
    }, 2000);
  });

  // ✅ Centered popup function
  function showPopupMessage(message) {
    const popup = document.createElement("div");
    popup.textContent = message;
    popup.style.position = "fixed";
    popup.style.top = "50%";
    popup.style.left = "50%";
    popup.style.transform = "translate(-50%, -50%)";
    popup.style.backgroundColor = "#083c34";
    popup.style.color = "white";
    popup.style.padding = "15px 25px";
    popup.style.borderRadius = "10px";
    popup.style.fontWeight = "500";
    popup.style.boxShadow = "0 3px 10px rgba(0,0,0,0.2)";
    popup.style.zIndex = "2000";
    document.body.appendChild(popup);

    setTimeout(() => popup.remove(), 1500);}

//privacy
 // When the "Privacy Policy/Terms" card is clicked
  document.getElementById("privacyPolicy").addEventListener("click", () => {
    const modal = new bootstrap.Modal(document.getElementById("privacyPolicyModal"));
    modal.show();
  });

  // Handle Accept button
  document.getElementById("acceptPolicyBtn").addEventListener("click", () => {
    // Close modal
    const modalEl = document.getElementById("privacyPolicyModal");
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    modalInstance.hide();

    // Optional redirect
    setTimeout(() => {
      window.location.href = "settings.php";
    }, 2000);
  });

  // ✅ Reuse popup message function
  function showPopupMessage(message) {
    const popup = document.createElement("div");
    popup.textContent = message;
    popup.style.position = "fixed";
    popup.style.top = "50%";
    popup.style.left = "50%";
    popup.style.transform = "translate(-50%, -50%)";
    popup.style.backgroundColor = "#083c34";
    popup.style.color = "white";
    popup.style.padding = "15px 25px";
    popup.style.borderRadius = "10px";
    popup.style.fontWeight = "500";
    popup.style.boxShadow = "0 3px 10px rgba(0,0,0,0.2)";
    popup.style.zIndex = "2000";
    document.body.appendChild(popup);
    setTimeout(() => popup.remove(), 1500);
  } 



  