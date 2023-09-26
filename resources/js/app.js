import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();


// Bespoke Js scripts

const hamburger = document.querySelector(".hamburger");
const mobilenav = document.querySelector(".mobile-menu");
const close = document.querySelector(".mobile-nav-close");


    // Add event Listener to close mobile nav
    close.addEventListener("click", () => {
        mobilenav.classList.toggle("hidden");
    });

    // Add event listener for hamburger 
    hamburger.addEventListener("click", () => {
        mobilenav.classList.toggle("hidden");
    });


// Open and close the user menu in the main navigation

const userMenuTop = document.getElementById('user-menu-top');
const userMenu = document.getElementById('user-menu');

userMenuTop.addEventListener('click', () => {
    userMenu.classList.toggle('hidden');
});
