import './bootstrap';
import {
  ArrowRight,
  Building2,
  CheckCircle2,
  ClipboardCheck,
  ClipboardPaste,
  Copy,
  createIcons,
  ExternalLink,
  Eye,
  FilePenLine,
  FileSearch,
  FileText,
  FolderPlus,
  KeyRound,
  LogOut,
  PencilLine,
  RefreshCw,
  Save,
  ShieldCheck,
  Sparkles,
  Upload,
  UserRoundCheck,
  X,
} from 'lucide';

createIcons({
  icons: {
    ArrowRight,
    Building2,
    CheckCircle2,
    ClipboardCheck,
    ClipboardPaste,
    Copy,
    ExternalLink,
    Eye,
    FilePenLine,
    FileSearch,
    FileText,
    FolderPlus,
    KeyRound,
    LogOut,
    PencilLine,
    RefreshCw,
    Save,
    ShieldCheck,
    Sparkles,
    Upload,
    UserRoundCheck,
    X,
  },
});

document.querySelectorAll('[data-open-dialog]').forEach((button) => {
  button.addEventListener('click', () => {
    const dialog = document.getElementById(button.dataset.openDialog || '');
    if (dialog instanceof HTMLDialogElement) {
      dialog.showModal();
    }
  });
});

document.querySelectorAll('[data-close-dialog]').forEach((button) => {
  button.addEventListener('click', () => {
    button.closest('dialog')?.close();
  });
});

document.querySelectorAll('dialog').forEach((dialog) => {
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) {
      dialog.close();
    }
  });
});

document.querySelectorAll('[data-person-type-choice]').forEach((choice) => {
  const form = choice.closest('form');
  if (!form) return;

  const radios = Array.from(choice.querySelectorAll('input[name="tipo_persona"]'));
  const panels = Array.from(form.querySelectorAll('[data-person-type-panel]'));

  const syncPanels = () => {
    const selected = radios.find((radio) => radio.checked)?.value || 'natural';
    panels.forEach((panel) => {
      const active = panel.dataset.personTypePanel === selected;
      panel.hidden = !active;
      panel.querySelectorAll('input, select, textarea').forEach((control) => {
        control.disabled = !active;
      });
    });
  };

  radios.forEach((radio) => radio.addEventListener('change', syncPanels));
  syncPanels();
});

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
        const maxDimension = 1600;
        const scale = Math.min(1, maxDimension / Math.max(image.naturalWidth, image.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(image.naturalWidth * scale);
        canvas.height = Math.round(image.naturalHeight * scale);

        const context = canvas.getContext('2d');
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        const jpegDataUrl = canvas.toDataURL('image/jpeg', 0.78);
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

const identityScanSteps = [
  {
    key: 'cedula_frontal',
    title: 'Cédula - lado frontal',
    instruction: 'Coloque el lado frontal de la cédula y presione Escanear.',
  },
  {
    key: 'cedula_posterior',
    title: 'Cédula - lado posterior',
    instruction: 'Coloque el lado posterior de la cédula y presione Continuar.',
  },
  {
    key: 'papeleta_votacion_frontal',
    title: 'Certificado de votación - lado frontal',
    instruction: 'Coloque el lado frontal del certificado de votación y presione Continuar.',
  },
  {
    key: 'papeleta_votacion_posterior',
    title: 'Certificado de votación - lado posterior',
    instruction: 'Coloque el lado posterior del certificado de votación y presione Finalizar.',
  },
];

const minorIdScanSteps = [
  {
    key: 'cedula_menor_frontal',
    title: 'Cédula del menor - lado frontal',
    instruction: 'Coloque el lado frontal de la cédula del menor y presione Escanear.',
  },
  {
    key: 'cedula_menor_posterior',
    title: 'Cédula del menor - lado posterior',
    instruction: 'Coloque el lado posterior de la cédula del menor y presione Finalizar.',
  },
];

const singleScanSteps = [
  {
    key: 'documento',
    title: 'Documento escaneado',
    instruction: 'Coloque el documento en el escáner y presione Escanear.',
  },
];

const buildMultiPageScanSteps = (maxPages = 6) => Array.from({ length: Math.max(1, Math.min(maxPages, 6)) }, (_, index) => ({
  key: `pagina_${index + 1}`,
  title: `Página ${index + 1}`,
  instruction: index === 0
    ? 'Coloque la primera página del documento en el escáner y presione Escanear.'
    : `Coloque la página ${index + 1} si aplica y presione Agregar página. También puede generar el PDF con las páginas ya escaneadas.`,
  required: index === 0,
}));

const scannerDialog = document.getElementById('scanner-dialog');

if (scannerDialog instanceof HTMLDialogElement) {
  const title = scannerDialog.querySelector('#scanner-dialog-title');
  const stepCounter = scannerDialog.querySelector('[data-scanner-step-counter]');
  const status = scannerDialog.querySelector('[data-scanner-status]');
  const instruction = scannerDialog.querySelector('[data-scanner-instruction]');
  const errorBox = scannerDialog.querySelector('[data-scanner-error]');
  const previews = scannerDialog.querySelector('[data-scanner-previews]');
  const nextButton = scannerDialog.querySelector('[data-scanner-next]');
  const finishButton = scannerDialog.querySelector('[data-scanner-finish]');
  const cancelButton = scannerDialog.querySelector('[data-scanner-cancel]');
  const closeButton = scannerDialog.querySelector('[data-scanner-close]');

  let scanState = null;

  const csrfToken = () => document.querySelector('input[name="_token"]')?.value || '';

  const showScannerError = (message) => {
    if (!errorBox) return;
    errorBox.hidden = false;
    errorBox.textContent = message;
  };

  const clearScannerError = () => {
    if (!errorBox) return;
    errorBox.hidden = true;
    errorBox.textContent = '';
  };

  const buttonLabel = () => {
    if (!scanState) return 'Escanear';
    if (scanState.readyToSubmit) return scanState.steps.length > 1 ? 'Generar PDF consolidado' : 'Generar PDF';
    if (scanState.currentIndex === 0) return 'Escanear';
    if (scanState.allowPartialSubmit) return 'Agregar página';
    return scanState.currentIndex === scanState.steps.length - 1 ? 'Finalizar' : 'Continuar';
  };

  const dataUrlToObjectUrl = (dataUrl) => {
    const matches = dataUrl.match(/^data:(image\/jpe?g);base64,([A-Za-z0-9+/=]+)$/i);
    if (!matches) return null;

    const binary = atob(matches[2]);
    const bytes = new Uint8Array(binary.length);

    for (let index = 0; index < binary.length; index += 1) {
      bytes[index] = binary.charCodeAt(index);
    }

    return URL.createObjectURL(new Blob([bytes], { type: matches[1] }));
  };

  const formatBytes = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} bytes`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
  };

  const renderScanner = () => {
    if (!scanState) return;

    const currentStep = scanState.steps[scanState.currentIndex];
    title.textContent = scanState.requirementLabel || 'Escanear documento';
    stepCounter.textContent = `Paso ${scanState.currentIndex + 1} de ${scanState.steps.length}`;
    status.textContent = scanState.readyToSubmit
      ? 'Capturas listas para revisar'
      : (scanState.busy ? 'Escaneando documento...' : currentStep.title);
    instruction.textContent = scanState.readyToSubmit
      ? (scanState.steps.length > 1
        ? 'Revise las capturas. Si están correctas, genere un solo PDF consolidado.'
        : 'Revise la imagen. Si está correcta, genere el PDF del documento.')
      : currentStep.instruction;
    nextButton.disabled = scanState.busy;
    nextButton.innerHTML = scanState.busy
      ? 'Escaneando...'
      : `<i data-lucide="file-search"></i> ${buttonLabel()}`;
    if (finishButton) {
      const canFinishPartial = scanState.allowPartialSubmit && Object.keys(scanState.captures).length > 0 && !scanState.readyToSubmit;
      finishButton.hidden = !canFinishPartial;
      finishButton.disabled = scanState.busy;
    }

    previews.innerHTML = scanState.steps.map((step, index) => {
      const capture = scanState.captures[step.key];
      return `
        <article class="scanner-preview ${capture ? 'has-scan' : ''}">
          <strong>${index + 1}. ${step.title}</strong>
          ${capture ? `<img src="${capture.previewUrl || capture.image}" alt="${step.title}" data-fallback-src="${capture.image}"><small>${formatBytes(capture.bytes)}</small>` : `<span>${step.required === false ? 'Opcional' : 'Pendiente'}</span>`}
          ${capture ? `
            <div class="scanner-preview-actions">
              <a class="doc-action" href="${capture.previewUrl || capture.image}" target="_blank" rel="noopener" aria-label="Ver ${step.title}" title="Ver imagen"><i data-lucide="eye"></i></a>
              <button class="doc-action" type="button" data-repeat-scan="${index}" aria-label="Repetir ${step.title}" title="Repetir captura"><i data-lucide="refresh-cw"></i></button>
            </div>
          ` : ''}
        </article>
      `;
    }).join('');

    createIcons({
      icons: {
        ArrowRight,
        Building2,
        CheckCircle2,
        ClipboardCheck,
        ClipboardPaste,
        Copy,
        ExternalLink,
        Eye,
        FilePenLine,
        FileSearch,
        FileText,
        FolderPlus,
        KeyRound,
        LogOut,
        PencilLine,
        RefreshCw,
        Save,
        ShieldCheck,
        Sparkles,
        Upload,
        UserRoundCheck,
        X,
      },
    });
  };

  const requestLocalScan = async (step) => {
    if (!scanState.scannerUrl) {
      throw new Error('No está configurada la URL del servicio local de escaneo.');
    }

    let response;
    try {
      response = await fetch(scanState.scannerUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          key: step.key,
          title: step.title,
          instruction: step.instruction,
          auto_crop: Boolean(scanState.autoCrop),
          scan_profile: scanState.scanProfile,
          fixed_scan_area: scanState.scanProfile === 'identity_92x165',
          page_width_mm: scanState.scanProfile === 'identity_92x165' ? 92 : null,
          page_height_mm: scanState.scanProfile === 'identity_92x165' ? 165 : null,
          dpi: scanState.scanProfile === 'identity_92x165' ? 300 : null,
          jpeg_quality: scanState.scanProfile === 'identity_92x165' ? 90 : null,
          max_side: scanState.scanProfile === 'identity_92x165' ? 2400 : null,
        }),
      });
    } catch (error) {
      throw new Error('No se pudo acceder al escáner. Verifique que el servicio local esté abierto.');
    }

    if (!response.ok) {
      const payload = await response.json().catch(() => ({}));
      throw new Error(payload.message || 'El servicio local de escaneo no respondió correctamente.');
    }

    const payload = await response.json();
    const image = payload.image || payload.imageData || payload.dataUrl;

    if (!image || !/^data:image\/jpe?g;base64,/i.test(image)) {
      throw new Error('La imagen escaneada está vacía o no es JPG.');
    }

    const servicePreviewUrl = payload.preview_url || payload.previewUrl || payload.preview || payload.url || null;
    const previewUrl = servicePreviewUrl || dataUrlToObjectUrl(image);
    const base64 = image.split(',', 2)[1] || '';
    const bytes = Math.floor((base64.length * 3) / 4);

    return { image, previewUrl, bytes };
  };

  const submitScannedDocument = async () => {
    if (scanState.requiresSignature && scanState.signatureCheckbox && !scanState.signatureCheckbox.checked) {
      showScannerError('Marque "Firma validada" antes de finalizar el escaneo de este documento.');
      return;
    }

    const requiredSteps = scanState.steps.filter((step) => step.required !== false);
    const missing = requiredSteps.filter((step) => !scanState.captures[step.key]);
    if (missing.length) {
      showScannerError(scanState.steps.length > 1
        ? 'Debe completar las capturas obligatorias para finalizar.'
        : 'Debe completar la captura para finalizar.');
      return;
    }

    clearScannerError();
    scanState.busy = true;
    status.textContent = 'Validando documento...';
    nextButton.disabled = true;

    const dynamicPayload = {
      ...(scanState.payload || {}),
      ...(scanState.signatureCheckbox ? { manual_signature_confirmed: scanState.signatureCheckbox.checked ? '1' : '' } : {}),
      ...(scanState.statusSelect ? { status: scanState.statusSelect.value || 'cargado' } : {}),
    };

    const response = await fetch(scanState.uploadUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify({
        ...dynamicPayload,
        captures: scanState.steps.filter((step) => scanState.captures[step.key]).map((step) => ({
          key: step.key,
          title: step.title,
          image: scanState.captures[step.key].image,
        })),
      }),
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
      scanState.busy = false;
      renderScanner();
      showScannerError(payload.message || 'No se pudo validar el documento.');
      return;
    }

    status.textContent = payload.message || 'Documento escaneado correctamente.';
    window.location.href = payload.redirect || window.location.href;
  };

  nextButton?.addEventListener('click', async () => {
    if (!scanState || scanState.busy) return;

    if (scanState.readyToSubmit) {
      await submitScannedDocument();
      return;
    }

    const currentStep = scanState.steps[scanState.currentIndex];
    clearScannerError();
    scanState.busy = true;
    renderScanner();

    try {
      const capture = await requestLocalScan(currentStep);
      scanState.captures[currentStep.key] = capture;
      scanState.busy = false;

      if (scanState.currentIndex < scanState.steps.length - 1) {
        scanState.currentIndex += 1;
        renderScanner();
        status.textContent = 'Documento escaneado correctamente.';
        return;
      }

      scanState.readyToSubmit = true;
      renderScanner();
      status.textContent = 'Revise las imágenes antes de generar el PDF.';
    } catch (error) {
      scanState.busy = false;
      renderScanner();
      showScannerError(error.message || 'El escaneo fue cancelado.');
    }
  });

  finishButton?.addEventListener('click', async () => {
    if (!scanState || scanState.busy) return;
    scanState.readyToSubmit = true;
    renderScanner();
    await submitScannedDocument();
  });

  previews?.addEventListener('click', (event) => {
    const repeatButton = event.target.closest('[data-repeat-scan]');
    if (!repeatButton || !scanState) return;

    scanState.currentIndex = Number(repeatButton.dataset.repeatScan || 0);
    delete scanState.captures[scanState.steps[scanState.currentIndex].key];
    scanState.readyToSubmit = false;
    clearScannerError();
    renderScanner();
  });

  previews?.addEventListener('error', (event) => {
    const image = event.target;
    if (!(image instanceof HTMLImageElement)) return;

    const fallback = image.dataset.fallbackSrc;
    if (fallback && image.src !== fallback) {
      image.src = fallback;
      return;
    }

    showScannerError('La captura llegó al sistema, pero el navegador no pudo mostrar la vista previa. Use el botón de ver imagen o continúe si la copia del escáner es correcta.');
  }, true);

  const closeScanner = () => {
    scanState = null;
    scannerDialog.close();
  };

  cancelButton?.addEventListener('click', closeScanner);
  closeButton?.addEventListener('click', closeScanner);

  const startScanner = (button, payload, steps = singleScanSteps) => {
    const form = button.closest('form');
    const signatureCheckbox = form?.querySelector('input[name="manual_signature_confirmed"]');
    const statusSelect = form?.querySelector('select[name="status"]');
    const isIdentityScan = steps === identityScanSteps || steps === minorIdScanSteps;

    scanState = {
      requirementLabel: button.dataset.documentLabel || button.dataset.requirementLabel || 'Escanear documento',
      payload: { ...payload },
      uploadUrl: button.dataset.scanUrl,
      scannerUrl: button.dataset.scannerUrl,
      requiresSignature: button.dataset.requiresSignature === '1',
      signatureCheckbox,
      statusSelect,
      steps,
      autoCrop: isIdentityScan,
      scanProfile: isIdentityScan ? 'identity_92x165' : 'document',
      allowPartialSubmit: button.dataset.scanMode === 'multi-page',
      captures: {},
      currentIndex: 0,
      busy: false,
      readyToSubmit: false,
    };

    clearScannerError();
    renderScanner();
    scannerDialog.showModal();
  };

  document.querySelectorAll('[data-scan-requirement]').forEach((button) => {
    button.addEventListener('click', () => {
      const slug = button.dataset.requirementSlug || '';
      startScanner(
        button,
        { account_type_requirement_id: button.dataset.requirementId },
        ['cedula', 'cedula-papeleta'].includes(slug)
          ? identityScanSteps
          : (slug === 'cedula-menor' ? minorIdScanSteps : singleScanSteps),
      );
    });
  });

  document.querySelectorAll('[data-scan-document]').forEach((button) => {
    button.addEventListener('click', () => {
      const maxPages = Number(button.dataset.maxPages || 6);
      startScanner(
        button,
        button.dataset.templateId ? { internal_document_template_id: button.dataset.templateId } : {},
        button.dataset.scanMode === 'multi-page' ? buildMultiPageScanSteps(maxPages) : singleScanSteps,
      );
    });
  });
}

document.querySelectorAll('.external-evidence-form').forEach((form) => {
  const companyChoice = form.querySelector('input[name="company_check_applicable"]');
  const companySection = form.querySelector('[data-external-subject="empresa"]');

  const syncCompanySection = () => {
    if (!companyChoice || !companySection) return;

    const enabled = companyChoice.checked;
    companySection.hidden = !enabled;
    companySection.querySelectorAll('input, select').forEach((control) => {
      control.disabled = !enabled;
      if (control.classList.contains('pasted-evidence-input')) {
        control.required = enabled && control.dataset.hasEvidence !== '1';
      }
    });
  };

  companyChoice?.addEventListener('change', syncCompanySection);
  syncCompanySection();

  form.addEventListener('submit', (event) => {
    const inputs = Array.from(form.querySelectorAll('.pasted-evidence-input:not(:disabled)'));
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

document.querySelectorAll('form[data-requires-signature="1"]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    const confirmation = form.querySelector('input[name="manual_signature_confirmed"]');
    if (confirmation?.checked) return;

    event.preventDefault();
    const label = form.dataset.signatureLabel || 'documento';
    window.alert(`El ${label} debe contener una firma. Revise visualmente el archivo y marque "Firma validada" antes de continuar.`);
    confirmation?.focus();
  });
});

document.querySelectorAll('.copy-extracted').forEach((button) => {
  button.addEventListener('click', async () => {
    const input = document.getElementById(button.dataset.copyTarget || '');
    if (!(input instanceof HTMLInputElement)) return;

    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(input.value);
      } else {
        input.select();
        document.execCommand('copy');
      }

      button.classList.add('is-copied');
      button.title = 'Copiado';
      window.setTimeout(() => {
        button.classList.remove('is-copied');
        button.title = 'Copiar';
      }, 1200);
    } catch {
      input.select();
      document.execCommand('copy');
    }
  });
});

document.querySelectorAll('form').forEach((form) => {
  form.addEventListener('submit', (event) => {
    requestAnimationFrame(() => {
      if (event.defaultPrevented) return;

      const button = event.submitter;
      if (!(button instanceof HTMLButtonElement)) return;

      button.disabled = true;
      button.classList.add('is-loading');
      button.dataset.originalLabel = button.textContent.trim();
      button.textContent = 'Guardando...';
    });
  });
});
