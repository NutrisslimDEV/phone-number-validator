document.addEventListener('DOMContentLoaded', function() {
    var phoneInput = document.querySelector('input[name="billing_phone"]');
    if (!phoneInput || typeof pnv_data === 'undefined') return;

    var prefix = pnv_data.prefix || ''; // e.g. "07"
    var maxLength = parseInt(pnv_data.maxLength, 10) || 0;

    // Wrap input with flag
    wrapInputWithFlag(phoneInput);

    // Set validation icon height to match input height for proper alignment
    setValidationIconHeight(phoneInput);

    // Initialize with prefix if empty
    if (!phoneInput.value || phoneInput.value.trim() === '') {
        phoneInput.value = prefix;
    }

    // Set maxlength attribute
    if (maxLength > 0) {
        phoneInput.setAttribute('maxlength', maxLength);
    }

    // Auto-correction and validation on input
    phoneInput.addEventListener('input', function(e) {
        var value = this.value;

        // Restore prefix if removed by paste or drag
        if (prefix && !this.value.startsWith(prefix)) {
            var cleaned = this.value.replace(/^\+?/, '');
            if (cleaned.startsWith(prefix)) {
                this.value = cleaned;
            } else {
                this.value = prefix + cleaned;
            }
        }

        // Trim to maxlength
        if (maxLength > 0 && this.value.length > maxLength) {
            this.value = this.value.slice(0, maxLength);
        }

        // Real-time validation (but don't show errors while typing)
        validatePhoneNumber(this, false);
    });

    // Final validation on blur (show errors)
    phoneInput.addEventListener('blur', function() {
        validatePhoneNumber(this, true);
    });

    // Prevent deleting the prefix
    phoneInput.addEventListener('keydown', function(e) {
        if (prefix && this.selectionStart <= prefix.length && (e.key === 'Backspace' || e.key === 'Delete')) {
            e.preventDefault();
        }
    });

    // Clear validation states when user focuses (starts editing again)
    phoneInput.addEventListener('focus', function() {
        if (this.classList.contains('invalid')) {
            this.classList.remove('invalid');
            this.parentNode.classList.remove('has-invalid');
            hideError(this);
        }
    });
});

/**
 * Wraps the phone input with a flag icon
 */
function wrapInputWithFlag(input) {
    // Check if already wrapped
    if (input.parentNode.classList.contains('phone-input-wrapper')) {
        return;
    }

    // Create wrapper div
    var wrapper = document.createElement('div');
    wrapper.className = 'phone-input-wrapper';

    // Create flag image
    var flag = document.createElement('img');
    flag.className = 'country-flag';
    flag.setAttribute('src', 'https://flagcdn.com/24x18/ro.png'); // Romanian flag from CDN
    flag.setAttribute('srcset', 'https://flagcdn.com/48x36/ro.png 2x'); // Retina display support
    flag.setAttribute('alt', 'România');
    flag.setAttribute('aria-label', 'România');

    // Insert wrapper before input
    input.parentNode.insertBefore(wrapper, input);

    // Move input into wrapper and add flag
    wrapper.appendChild(flag);
    wrapper.appendChild(input);
}

/**
 * Sets validation icon height to match the input field height for proper alignment
 */
function setValidationIconHeight(input) {
    // Wait for next frame to ensure input is rendered
    setTimeout(function() {
        var wrapper = input.parentNode;
        var inputHeight = input.offsetHeight;

        if (inputHeight > 0) {
            // Set CSS variable for the ::after pseudo-element (validation checkmark/X)
            wrapper.style.setProperty('--input-height', inputHeight + 'px');
        }
    }, 0);
}

/**
 * Validates the phone number format
 * @param {HTMLInputElement} input - The phone input element
 * @param {boolean} showErrors - Whether to display error messages
 */
function validatePhoneNumber(input, showErrors) {
    // Romanian mobile pattern: 07 followed by 8 more digits
    var pattern = /^07\d{8}$/;
    var value = input.value.replace(/\s/g, ''); // Remove spaces for validation
    var wrapper = input.parentNode;

    if (pattern.test(value)) {
        // Valid number
        input.classList.remove('invalid');
        input.classList.add('valid');
        wrapper.classList.remove('has-invalid');
        wrapper.classList.add('has-valid', 'has-icon');
        hideError(input);
        return true;
    } else if (showErrors && value.length >= 10) {
        // Invalid number (only show errors on blur and if user has typed enough)
        input.classList.remove('valid');
        input.classList.add('invalid');
        wrapper.classList.remove('has-valid');
        wrapper.classList.add('has-invalid', 'has-icon');

        // Determine specific error message
        var errorMsg;
        if (value.startsWith('07') && value.length >= 3) {
            errorMsg = 'Format de număr de telefon invalid';
        } else {
            errorMsg = 'Introduceți un număr de telefon mobil românesc valid în formatul 07XXXXXXXX';
        }

        showError(input, errorMsg);
        return false;
    } else {
        // Neutral state (still typing or too short)
        input.classList.remove('valid', 'invalid');
        wrapper.classList.remove('has-valid', 'has-invalid', 'has-icon');
        hideError(input);
        return null;
    }
}

/**
 * Displays an error message below the input
 */
function showError(input, message) {
    var wrapper = input.parentNode;
    var errorEl = wrapper.querySelector('.phone-error');

    if (!errorEl) {
        errorEl = document.createElement('div');
        errorEl.className = 'phone-error';
        wrapper.appendChild(errorEl);
    }

    errorEl.textContent = message;
    errorEl.style.display = 'block';
}

/**
 * Hides the error message
 */
function hideError(input) {
    var wrapper = input.parentNode;
    var errorEl = wrapper.querySelector('.phone-error');

    if (errorEl) {
        errorEl.style.display = 'none';
    }
}
