/**
 * Intended use is to toggle the visibility of a class of divs which contain images.
 * Expects a custom attribute on the image elements that stores a path to their 
 * source data. Also toggles the text on the provided buttons.
 * 
 * @param imgState 
 * @param toggleButtons 
 * @param imageDivs 
 * @param images 
 * @param imgSrcAttrName 
 * @param dispPropertyName 
 * @param dispHidePropertyName 
 * @param hideButtonText 
 * @param showButtonText 
 */ 
export function toggleImageDiv(imgState, 
                               toggleButtons, 
                               imageDivs, 
                               images, 
                               imgSrcAttrName, 
                               dispPropertyName, 
                               dispHidePropertyName, 
                               hideButtonText, 
                               showButtonText) {

    if (imgState.hidden) {

        if (!imgState.loaded) {
            images.forEach(
                (img) => img.setAttribute("src", img.getAttribute(imgSrcAttrName))
            );
            imgState.loaded = true;
        }

        imageDivs.forEach(
            (div) => div.style.display = getComputedStyle(div)
                                            .getPropertyValue(dispPropertyName)
        );

        toggleButtons.forEach((btn) => btn.textContent = hideButtonText);

    } else {

        imageDivs.forEach(
            (div) => div.style.display = getComputedStyle(div)
                                            .getPropertyValue(dispHidePropertyName)
        );

        toggleButtons.forEach((btn) => btn.textContent = showButtonText);
    }

    imgState.hidden = !imgState.hidden;
}

export function addImageDivToggleListeners(thumbnailState, 
                                           toggleBtnClass,
                                           imageDivClass,
                                           imageClass,
                                           imgSrcAttrName, 
                                           dispPropertyName, 
                                           dispHidePropertyName, 
                                           hideButtonText, 
                                           showButtonText) {
    const toggleButtons = document.querySelectorAll(toggleBtnClass);
    const imageDivs = document.querySelectorAll(imageDivClass);
    const images = document.querySelectorAll(imageClass);
    toggleButtons.forEach(
            (btn) => btn.addEventListener("click", 
                                          () => toggleImageDiv(thumbnailState,
                                                               toggleButtons, 
                                                               imageDivs, 
                                                               images, 
                                                               imgSrcAttrName, 
                                                               dispPropertyName, 
                                                               dispHidePropertyName, 
                                                               hideButtonText, 
                                                               showButtonText)
                                         )
    );  
}