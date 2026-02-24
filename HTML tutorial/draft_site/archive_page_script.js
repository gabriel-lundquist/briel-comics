// Start thumbnails as showing
let nailsHidden = Boolean(false);

function toggleNails() {
    const toggleNailsButtons = document.querySelectorAll(".toggle-nails");
    if (nailsHidden) {
        for (const toggleNailsButton of toggleNailsButtons) {
            toggleNailsButton.textContent = "Hide thumbnails";
        }
        
    } else {
        for (const toggleNailsButton of toggleNailsButtons) {
            toggleNailsButton.textContent = "Show thumbnails";
        }
    }
    nailsHidden = !nailsHidden;
}

const toggleNailsButtons = document.querySelectorAll(".toggle-nails");
for (const toggleNailsButton of toggleNailsButtons) {
    toggleNailsButton.addEventListener("click", toggleNails)
}
