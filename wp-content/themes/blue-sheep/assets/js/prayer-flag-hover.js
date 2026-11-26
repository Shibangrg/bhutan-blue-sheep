document.addEventListener('DOMContentLoaded', function () {

    const navigation = document.querySelector('.site-navigation');
    const settings = window.blueSheepPrayerFlag;

    if (!navigation || !settings) {
        return;
    }

    const currentPath = normalizePath(window.location.pathname);
    const links = navigation.querySelectorAll('a');

    function normalizePath(path) {
        const normalized = path
            .replace(/\/+/g, '/')
            .replace(/\/+$/, '');

        return normalized || '/';
    }

    function isCurrentPage(link) {

        // First check WordPress menu classes.
        const currentMenuItem = link.closest(
            'li.current-menu-item, ' +
            'li.current_page_item, ' +
            'li.current-menu-ancestor, ' +
            'li.current-menu-parent, ' +
            'li.current_page_parent, ' +
            'li.current_page_ancestor'
        );

        if (
            currentMenuItem ||
            link.getAttribute('aria-current') === 'page'
        ) {
            return true;
        }

        // Also check whether the current URL belongs to this menu section.
        try {
            const linkUrl = new URL(
                link.href,
                window.location.origin
            );

            if (linkUrl.origin !== window.location.origin) {
                return false;
            }

            const linkPath = normalizePath(linkUrl.pathname);

            if (linkPath === '/') {
                return currentPath === '/';
            }

            return (
                currentPath === linkPath ||
                currentPath.startsWith(linkPath + '/')
            );

        } catch (error) {
            return false;
        }
    }

    links.forEach(function (link) {

        if (link.dataset.prayerFlagReady === 'true') {
            return;
        }

        link.dataset.prayerFlagReady = 'true';

        const selected = isCurrentPage(link);

        // Wrap the navigation text.
        const label = document.createElement('span');
        label.className = 'nav-link__label';

        while (link.firstChild) {
            label.appendChild(link.firstChild);
        }

        // Selected page: display static flag image.
        if (selected) {

            const image = document.createElement('img');

            image.className = 'nav-link__flag-image';
            image.src = settings.imageUrl;
            image.alt = '';
            image.setAttribute('aria-hidden', 'true');

            link.appendChild(image);
            link.appendChild(label);

            link.classList.add(
                'has-prayer-flag',
                'is-current-page'
            );

            return;
        }

        // Other pages: animated flag on hover.
        const video = document.createElement('video');

        video.className = 'nav-link__flag-video';
        video.muted = true;
        video.loop = true;
        video.playsInline = true;
        video.preload = 'none';
        video.setAttribute('aria-hidden', 'true');
        video.tabIndex = -1;

        const source = document.createElement('source');

        source.src = settings.videoUrl;
        source.type = 'video/mp4';

        video.appendChild(source);

        link.appendChild(video);
        link.appendChild(label);

        link.classList.add('has-prayer-flag');

        function activateFlag() {

            link.classList.add('is-flag-active');

            if (video.readyState === 0) {
                video.load();
            }

            const playback = video.play();

            if (playback) {
                playback.catch(function (error) {
                    console.warn(
                        'Prayer flag playback failed:',
                        error
                    );
                });
            }
        }

        function deactivateFlag() {

            link.classList.remove('is-flag-active');

            video.pause();

            if (video.readyState > 0) {
                video.currentTime = 0;
            }
        }

        link.addEventListener('mouseenter', activateFlag);
        link.addEventListener('mouseleave', deactivateFlag);
        link.addEventListener('focus', activateFlag);
        link.addEventListener('blur', deactivateFlag);

    });

});