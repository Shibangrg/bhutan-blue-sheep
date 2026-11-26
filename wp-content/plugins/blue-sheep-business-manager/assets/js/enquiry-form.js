document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('.bsbm-enquiry-form');

    forms.forEach(function (form) {
        const enquiryType = form.querySelector('#bsbm-enquiry-type');

        const tripFields = form.querySelector(
            '[data-bsbm-trip-fields]'
        );

        const packageFields = form.querySelector(
            '[data-bsbm-package-fields]'
        );

        const tourSelect = form.querySelector('#bsbm-tour');
        const travellerCount = form.querySelector('#bsbm-travellers');

        const adultsCount = form.querySelector('#bsbm-adults');
        const childrenCount = form.querySelector('#bsbm-children');

        const startDate = form.querySelector('#bsbm-start-date');
        const endDate = form.querySelector('#bsbm-end-date');

        if (
            !enquiryType ||
            !tripFields ||
            !packageFields
        ) {
            return;
        }

        /**
         * Enable or disable controls inside a section.
         */
        function setSectionState(section, enabled) {
            const controls = section.querySelectorAll(
                'input, select, textarea, button'
            );

            controls.forEach(function (control) {
                control.disabled = !enabled;
            });
        }

        /**
         * Update conditional form sections.
         */
        function updateFormVisibility() {
            const selectedType = enquiryType.value;

            const isTripEnquiry =
                selectedType === 'custom_trip' ||
                selectedType === 'package_reservation';

            const isPackageReservation =
                selectedType === 'package_reservation';

            tripFields.hidden = !isTripEnquiry;
            setSectionState(tripFields, isTripEnquiry);

            packageFields.hidden = !isPackageReservation;
            setSectionState(packageFields, isPackageReservation);

            // Traveller count is required for trip enquiries.
            if (travellerCount) {
                travellerCount.required = isTripEnquiry;
            }

            // A package reservation must identify its package.
            if (tourSelect) {
                tourSelect.required = isPackageReservation;
            }

            validateDates();
            validateTravellerCounts();
        }

        /**
         * Validate the requested travel dates.
         */
        function validateDates() {
            if (!startDate || !endDate) {
                return true;
            }

            startDate.setCustomValidity('');
            endDate.setCustomValidity('');

            if (
                startDate.value &&
                endDate.value &&
                endDate.value < startDate.value
            ) {
                endDate.setCustomValidity(
                    'The end date cannot be earlier than the start date.'
                );

                return false;
            }

            return true;
        }

        /**
         * Validate total, adult and child counts.
         *
         * If all three values are supplied, their totals
         * must be consistent.
         */
        function validateTravellerCounts() {
            if (
                !travellerCount ||
                !adultsCount ||
                !childrenCount
            ) {
                return true;
            }

            travellerCount.setCustomValidity('');
            adultsCount.setCustomValidity('');
            childrenCount.setCustomValidity('');

            const total = travellerCount.value;
            const adults = adultsCount.value;
            const children = childrenCount.value;

            if (
                total !== '' &&
                adults !== '' &&
                children !== ''
            ) {
                const totalValue = Number(total);
                const adultsValue = Number(adults);
                const childrenValue = Number(children);

                if (
                    adultsValue + childrenValue !== totalValue
                ) {
                    adultsCount.setCustomValidity(
                        'Adult and child counts must equal the total number of travellers.'
                    );

                    return false;
                }
            }

            return true;
        }

        /**
         * Validate fields while the customer is editing.
         */
        form.addEventListener('input', function () {
            validateDates();
            validateTravellerCounts();
        });

        form.addEventListener('change', function () {
            validateDates();
            validateTravellerCounts();
        });

        /**
         * Update visibility when the enquiry type changes.
         */
        enquiryType.addEventListener(
            'change',
            updateFormVisibility
        );

        /**
         * Final client-side validation before submission.
         */
        form.addEventListener('submit', function (event) {
            const datesValid = validateDates();
            const travellersValid = validateTravellerCounts();

            if (!datesValid || !travellersValid) {
                event.preventDefault();
                form.reportValidity();
            }
        });

        // Initialize the form based on its initial selection.
        updateFormVisibility();
    });
});
