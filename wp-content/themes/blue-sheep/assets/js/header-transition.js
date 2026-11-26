document.addEventListener('DOMContentLoaded', function () {

    const header = document.querySelector('.site-header');
    const video = document.querySelector('.site-brand__video');

    const menuToggle = document.querySelector('.site-navigation__toggle');
    const navigation = document.querySelector('.site-navigation');

    /*
     * HEADER SCROLL VIDEO TRANSITION
     */

    if (header && video) {

        let isScrolled = false;

        function updateHeader() {
            const shouldShowVideo = window.scrollY > 80;

            if (shouldShowVideo === isScrolled) {
                return;
            }

            isScrolled = shouldShowVideo;

            header.classList.toggle('is-scrolled', isScrolled);

            if (isScrolled) {
                video.play().catch(function (error) {
                    console.warn('Header video playback failed:', error);
                });
            } else {
                video.pause();
                video.currentTime = 0;
            }
        }

        window.addEventListener('scroll', updateHeader, {
            passive: true
        });

        updateHeader();
    }

    /*
     * MOBILE NAVIGATION
     */

    if (menuToggle && navigation) {

        function closeMenu() {
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.setAttribute('aria-label', 'Open navigation menu');
            navigation.classList.remove('is-open');
        }

        function openMenu() {
            menuToggle.setAttribute('aria-expanded', 'true');
            menuToggle.setAttribute('aria-label', 'Close navigation menu');
            navigation.classList.add('is-open');
        }

        menuToggle.addEventListener('click', function () {

            const isOpen =
                menuToggle.getAttribute('aria-expanded') === 'true';

            if (isOpen) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        navigation.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeMenu);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });

        document.addEventListener('click', function (event) {
            if (
                !navigation.contains(event.target) &&
                !menuToggle.contains(event.target)
            ) {
                closeMenu();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                closeMenu();
            }
        });
    }

});
