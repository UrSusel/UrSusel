
const modal = document.getElementById('imageModal');
const modalImage = document.getElementById('modalImage');
const modalInfo = document.getElementById('modalInfo');
const closeBtn = document.getElementsByClassName('close')[0];
const galleryItems = document.querySelectorAll('.gallery-item');


galleryItems.forEach(item => {
    item.addEventListener('click', function() {
        this.classList.add('animate');

        
        setTimeout(() => {
            modal.style.display = "flex"; 
            modalImage.src = this.src; 
            modalInfo.innerText = this.getAttribute('data-info'); 
        }, 500); 
    });
});

closeBtn.addEventListener('click', function() {
    modal.style.display = "none";
    galleryItems.forEach(item => {
        item.classList.remove('animate');
    });
});


window.addEventListener('click', function(event) {
    if (event.target == modal) {
        modal.style.display = "none";
        galleryItems.forEach(item => {
            item.classList.remove('animate');
        });
    }
});

const pageTitle = document.getElementById('pageTitle');


pageTitle.addEventListener('click', function() {
    
    this.classList.add('move');

   
    const colors = ['color1', 'color2', 'color3', 'color4', 'color5'];
    let colorIndex = 0;

    
    const colorChangeInterval = setInterval(() => {
        this.classList.remove(colors[colorIndex]);
        colorIndex = (colorIndex + 1) % colors.length; 
        this.classList.add(colors[colorIndex]);
    }, 200); 

    
    setTimeout(() => {
        clearInterval(colorChangeInterval); 
        this.classList.remove('move');
        this.classList.remove(colors[colorIndex]); 
        this.classList.add(colors[0]); 
    }, 5000);
});
const adPopup = document.getElementById('adPopup');
const closeAdBtn = document.getElementsByClassName('close-ad')[0];
function showAd() {
    adPopup.style.display = 'flex';
}
setTimeout(showAd, 1000);


setInterval(showAd, 30000); 

closeAdBtn.addEventListener('click', function() {
    adPopup.style.display = 'none';
});

window.addEventListener('click', function(event) {
    if (event.target === adPopup) {
        adPopup.style.display = 'none';
    }
});

