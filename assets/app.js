(() => {
    const dialog = document.getElementById('apps-dialog');
    const opener = document.querySelector('[data-apps-open]');

    if (dialog instanceof HTMLDialogElement && opener instanceof HTMLButtonElement) {
        opener.addEventListener('click', () => {
            dialog.showModal();
            opener.setAttribute('aria-expanded', 'true');
        });
        dialog.addEventListener('cancel', () => opener.focus());
        dialog.addEventListener('close', () => {
            opener.setAttribute('aria-expanded', 'false');
            opener.focus();
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    }

    document.querySelectorAll('[data-public-carousel]').forEach((carousel) => {
        if (!(carousel instanceof HTMLElement)) {
            return;
        }

        const track = carousel.querySelector('.public-carousel__track');
        if (!(track instanceof HTMLElement)) {
            return;
        }

        const slides = Array.from(track.querySelectorAll('[data-public-featured-unit]'))
            .filter((slide) => slide instanceof HTMLElement);
        if (slides.length < 2) {
            return;
        }

        const controls = document.createElement('div');
        controls.className = 'public-carousel__controls';
        controls.setAttribute('aria-label', 'Navegación de propiedades');

        const previous = document.createElement('button');
        previous.type = 'button';
        previous.className = 'public-carousel__arrow';
        previous.setAttribute('aria-label', 'Propiedad anterior');
        previous.textContent = '‹';

        const next = document.createElement('button');
        next.type = 'button';
        next.className = 'public-carousel__arrow';
        next.setAttribute('aria-label', 'Propiedad siguiente');
        next.textContent = '›';

        const dots = document.createElement('div');
        dots.className = 'public-carousel__dots';
        const dotButtons = slides.map((slide, index) => {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'public-carousel__dot';
            dot.setAttribute('aria-label', `Ir a propiedad ${index + 1}`);
            dots.append(dot);
            return dot;
        });

        controls.append(previous, dots, next);
        carousel.append(controls);

        let currentIndex = 0;
        let frame = 0;

        const sync = (index) => {
            currentIndex = Math.max(0, Math.min(slides.length - 1, index));
            previous.disabled = currentIndex === 0;
            next.disabled = currentIndex === slides.length - 1;
            dotButtons.forEach((dot, dotIndex) => {
                if (dotIndex === currentIndex) {
                    dot.setAttribute('aria-current', 'true');
                } else {
                    dot.removeAttribute('aria-current');
                }
            });
        };

        const scrollToIndex = (index) => {
            const targetIndex = Math.max(0, Math.min(slides.length - 1, index));
            slides[targetIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            sync(targetIndex);
        };

        previous.addEventListener('click', () => scrollToIndex(currentIndex - 1));
        next.addEventListener('click', () => scrollToIndex(currentIndex + 1));
        dotButtons.forEach((dot, index) => {
            dot.addEventListener('click', () => scrollToIndex(index));
        });

        track.addEventListener('scroll', () => {
            if (frame !== 0) {
                return;
            }

            frame = requestAnimationFrame(() => {
                frame = 0;
                const trackRect = track.getBoundingClientRect();
                const center = trackRect.left + (trackRect.width / 2);
                let nearestIndex = 0;
                let nearestDistance = Number.POSITIVE_INFINITY;

                slides.forEach((slide, index) => {
                    const rect = slide.getBoundingClientRect();
                    const distance = Math.abs((rect.left + (rect.width / 2)) - center);
                    if (distance < nearestDistance) {
                        nearestDistance = distance;
                        nearestIndex = index;
                    }
                });

                sync(nearestIndex);
            });
        }, { passive: true });

        sync(0);
    });

    const typeSelect = document.querySelector('[data-unit-type-select]');
    if (typeSelect instanceof HTMLSelectElement) {
        const fields = document.querySelectorAll('[data-unit-type-field]');
        const syncUnitTypeFields = () => {
            fields.forEach((field) => {
                if (field instanceof HTMLElement) {
                    field.hidden = field.dataset.unitTypeField !== typeSelect.value;
                }
            });
        };

        typeSelect.addEventListener('change', syncUnitTypeFields);
        syncUnitTypeFields();
    }
})();
