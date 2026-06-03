import './bootstrap';

document.querySelectorAll('input[type="file"]').forEach((input) => {
  input.addEventListener('change', () => {
    const file = input.files?.[0];
    if (!file) return;

    const allowed = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!allowed.includes(file.type)) {
      input.setCustomValidity('Solo se permiten archivos PDF, JPG o PNG.');
      input.reportValidity();
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      input.setCustomValidity('El archivo no debe superar 5 MB.');
      input.reportValidity();
      return;
    }

    input.setCustomValidity('');
  });
});

document.querySelectorAll('.paste-capture').forEach((zone) => {
  const form = zone.closest('form');
  const hiddenInput = zone.parentElement?.querySelector('.pasted-evidence-input') || form?.querySelector('.pasted-evidence-input');
  const preview = zone.querySelector('img');
  const label = zone.querySelector('span');

  zone.addEventListener('click', () => zone.focus());

  const showPasteError = (message) => {
    if (!label) return;
    zone.classList.add('paste-error');
    zone.classList.remove('has-image');
    label.textContent = message;
  };

  zone.addEventListener('paste', (event) => {
    const items = Array.from(event.clipboardData?.items || []);
    const imageItem = items.find((item) => item.type.startsWith('image/'));

    if (!imageItem || !hiddenInput || !preview || !label) {
      showPasteError('No se encontro una imagen en el portapapeles');
      return;
    }

    const file = imageItem.getAsFile();
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
      showPasteError('La imagen pegada no debe superar 5 MB');
      return;
    }

    const reader = new FileReader();
    reader.onload = () => {
      const image = new Image();
      image.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = image.naturalWidth;
        canvas.height = image.naturalHeight;

        const context = canvas.getContext('2d');
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0);

        const jpegDataUrl = canvas.toDataURL('image/jpeg', 0.9);
        const estimatedBytes = Math.ceil((jpegDataUrl.length - 'data:image/jpeg;base64,'.length) * 0.75);

        if (estimatedBytes > 5 * 1024 * 1024) {
          showPasteError('La imagen convertida a PDF no debe superar 5 MB');
          return;
        }

        hiddenInput.value = jpegDataUrl;
        preview.src = jpegDataUrl;
        preview.hidden = false;
        label.textContent = 'Captura pegada; se guardara como PDF';
        zone.classList.remove('paste-error');
        zone.classList.add('has-image');
      };
      image.onerror = () => showPasteError('No se pudo leer la captura pegada');
      image.src = reader.result;
    };
    reader.readAsDataURL(file);
  });
});

document.querySelectorAll('.external-evidence-form').forEach((form) => {
  form.addEventListener('submit', (event) => {
    const inputs = Array.from(form.querySelectorAll('.pasted-evidence-input'));
    const anyPasted = inputs.some((input) => input.value);
    const missingInput = inputs.find((input) => !input.value && (input.required || anyPasted));

    if (!missingInput) return;

    event.preventDefault();
    const zone = missingInput.parentElement?.querySelector('.paste-capture');
    const label = zone?.querySelector('span');

    if (zone && label) {
      zone.classList.add('paste-error');
      zone.focus();
      label.textContent = 'Pegue esta evidencia antes de guardar';
    }
  });
});
