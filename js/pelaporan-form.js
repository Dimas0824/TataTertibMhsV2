(function () {
    const form = document.getElementById('pelanggaranForm');
    const nimInput = document.getElementById('nim');
    const nimSuggestions = document.getElementById('nimSuggestions');
    const namaInput = document.getElementById('nama');
    const semesterInput = document.getElementById('semester');
    const prodiInput = document.getElementById('prodi');
    const nimHelpText = document.getElementById('nimHelpText');
    const tingkatSelect = document.getElementById('tingkat');
    const jenisPelanggaranSelect = document.getElementById('jenisPelanggaran');
    const sanksiSelect = document.getElementById('sanksi');
    const deskripsiTugasContainer = document.getElementById('deskripsiTugas-container');
    const deskripsiTugasInput = document.getElementById('deskripsiTugas');
    const penanggungTugasContainer = document.getElementById('penanggungTugas-container');
    const penanggungTugasSelect = document.getElementById('penanggungTugas');
    const isEditMode = Boolean(form && form.querySelector('input[name="id_detail"]'));

    if (!tingkatSelect || !jenisPelanggaranSelect) {
        return;
    }

    const allJenisOptions = Array.from(jenisPelanggaranSelect.options)
        .filter((option) => option.value !== '')
        .map((option) => ({
            value: option.value,
            text: option.textContent,
            tingkat: option.getAttribute('data-tingkat') || '',
        }));

    const allSanksiOptions = sanksiSelect
        ? Array.from(sanksiSelect.options)
            .filter((option) => option.value !== '')
            .map((option) => ({
                value: option.value,
                text: option.textContent,
                tingkat: option.getAttribute('data-tingkat') || '',
            }))
        : [];

    const refreshSelect2 = (selectElement, placeholder) => {
        if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.select2 !== 'function') {
            return;
        }

        const $element = window.jQuery(selectElement);
        if ($element.hasClass('select2-hidden-accessible')) {
            $element.select2('destroy');
        }

        $element.select2({
            width: '100%',
            placeholder,
            allowClear: true,
        });
    };

    const applySelectEnhancement = () => {
        refreshSelect2(jenisPelanggaranSelect, 'Cari jenis pelanggaran');
        if (sanksiSelect) {
            refreshSelect2(sanksiSelect, 'Pilih sanksi');
        }
    };

    const syncTaskResponsibilityState = (requiresTugasKhusus) => {
        const delegasiKeDpa = requiresTugasKhusus
            && penanggungTugasSelect
            && String(penanggungTugasSelect.value || '').trim().toLowerCase() === 'dpa';
        const lockDeskripsiByDelegasi = delegasiKeDpa && !isEditMode;

        if (deskripsiTugasInput) {
            deskripsiTugasInput.disabled = !requiresTugasKhusus || lockDeskripsiByDelegasi;
            if (!requiresTugasKhusus || lockDeskripsiByDelegasi) {
                deskripsiTugasInput.value = '';
            }
        }
    };

    const toggleTugasKhusus = (tingkat) => {
        const normalizedTingkat = String(tingkat || '').trim().toUpperCase();
        const requiresTugasKhusus = ['I', 'II', 'III', '1', '2', '3'].includes(normalizedTingkat);

        if (deskripsiTugasContainer) {
            deskripsiTugasContainer.style.display = requiresTugasKhusus ? 'block' : 'none';
        }

        if (penanggungTugasContainer) {
            penanggungTugasContainer.style.display = requiresTugasKhusus ? 'block' : 'none';
        }

        if (penanggungTugasSelect) {
            penanggungTugasSelect.disabled = !requiresTugasKhusus;
            if (!requiresTugasKhusus) {
                penanggungTugasSelect.value = 'dosen';
            }
        }

        syncTaskResponsibilityState(requiresTugasKhusus);
    };

    const setSelectOptionsByTingkat = (selectElement, options, tingkat, placeholderText) => {
        if (!selectElement) {
            return;
        }

        const previousValue = selectElement.value;
        selectElement.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = placeholderText;
        selectElement.appendChild(placeholder);

        const filteredOptions = tingkat
            ? options.filter((option) => option.tingkat === tingkat)
            : [];

        filteredOptions.forEach((optionData) => {
            const option = document.createElement('option');
            option.value = optionData.value;
            option.textContent = optionData.text;
            option.setAttribute('data-tingkat', optionData.tingkat);
            selectElement.appendChild(option);
        });

        const hasPreviousValue = filteredOptions.some((option) => option.value === previousValue);
        selectElement.value = hasPreviousValue ? previousValue : '';
        selectElement.disabled = !tingkat;
    };

    const calculateSemester = (angkatanValue) => {
        const angkatan = Number.parseInt(String(angkatanValue), 10);
        if (Number.isNaN(angkatan)) {
            return '';
        }

        const today = new Date();
        const currentYear = today.getFullYear();
        const currentMonth = today.getMonth() + 1;

        let semester = (currentYear - angkatan) * 2;
        if (currentMonth >= 8) {
            semester += 1;
        }

        return semester > 0 ? String(semester) : '';
    };

    const clearIdentity = (message) => {
        if (namaInput) namaInput.value = '';
        if (semesterInput) semesterInput.value = '';
        if (prodiInput) prodiInput.value = '';
        if (nimHelpText && message) {
            nimHelpText.textContent = message;
        }
    };

    const renderNimSuggestions = (items) => {
        if (!nimSuggestions) {
            return;
        }

        nimSuggestions.innerHTML = '';
        items.forEach((item) => {
            const nimValue = String(item && item.nim ? item.nim : '').trim();
            if (nimValue === '') {
                return;
            }

            const option = document.createElement('option');
            option.value = nimValue;

            const detailText = [
                String(item && item.nama_lengkap ? item.nama_lengkap : '').trim(),
                String(item && item.nama_prodi ? item.nama_prodi : '').trim(),
            ].filter(Boolean).join(' - ');

            if (detailText !== '') {
                option.label = detailText;
                option.textContent = detailText;
            }

            nimSuggestions.appendChild(option);
        });
    };

    const lookupEndpoint = form ? String(form.getAttribute('data-lookup-endpoint') || '') : '';
    const searchEndpoint = form ? String(form.getAttribute('data-search-endpoint') || '') : '';

    let resolvedNim = '';
    let lookupRequestId = 0;
    const lookupMahasiswa = async () => {
        if (!nimInput) {
            return;
        }

        const nim = nimInput.value.trim();
        const currentRequestId = ++lookupRequestId;

        if (nim === '') {
            resolvedNim = '';
            clearIdentity('Ketik minimal 2 karakter untuk mencari NIM mahasiswa.');
            return;
        }

        if (nimHelpText) {
            nimHelpText.textContent = 'Mengambil data mahasiswa...';
        }

        try {
            if (lookupEndpoint === '') {
                throw new Error('Lookup endpoint is not configured.');
            }

            const response = await fetch(lookupEndpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ nim }),
            });
            const payload = await response.json();

            if (currentRequestId !== lookupRequestId) {
                return;
            }

            if (!response.ok || !payload.success) {
                resolvedNim = '';
                clearIdentity(payload.message || 'Mahasiswa tidak ditemukan.');
                return;
            }

            const mahasiswa = payload.data || {};
            if (namaInput) namaInput.value = mahasiswa.nama_lengkap || '';
            if (prodiInput) prodiInput.value = mahasiswa.nama_prodi || '';
            if (semesterInput) semesterInput.value = calculateSemester(mahasiswa.angkatan);
            if (nimHelpText) nimHelpText.textContent = 'Data mahasiswa ditemukan.';
            resolvedNim = nim;
        } catch (_error) {
            if (currentRequestId !== lookupRequestId) {
                return;
            }
            resolvedNim = '';
            clearIdentity('Gagal mengambil data mahasiswa.');
        }
    };

    let searchRequestId = 0;
    const searchMahasiswa = async () => {
        if (!nimInput || !nimSuggestions) {
            return;
        }

        const keyword = nimInput.value.trim();
        const currentRequestId = ++searchRequestId;

        if (keyword.length < 2) {
            renderNimSuggestions([]);
            if (keyword === '' && nimHelpText) {
                nimHelpText.textContent = 'Ketik minimal 2 karakter untuk mencari NIM mahasiswa.';
            }
            return;
        }

        try {
            if (searchEndpoint === '') {
                return;
            }

            const connector = searchEndpoint.includes('?') ? '&' : '?';
            const requestUrl = searchEndpoint + connector + 'q=' + encodeURIComponent(keyword) + '&limit=12';
            const response = await fetch(requestUrl, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                },
            });
            const payload = await response.json();

            if (currentRequestId !== searchRequestId) {
                return;
            }

            if (!response.ok || !payload.success || !Array.isArray(payload.data)) {
                renderNimSuggestions([]);
                return;
            }

            renderNimSuggestions(payload.data);
        } catch (_error) {
            if (currentRequestId !== searchRequestId) {
                return;
            }
            renderNimSuggestions([]);
        }
    };

    let nimSearchDebounceTimer = null;
    if (nimInput) {
        nimInput.addEventListener('input', () => {
            if (nimInput.value.trim() !== resolvedNim) {
                clearIdentity('Pilih NIM dari daftar atau lanjutkan mengetik NIM lengkap.');
            }

            if (nimSearchDebounceTimer) {
                clearTimeout(nimSearchDebounceTimer);
            }

            nimSearchDebounceTimer = setTimeout(searchMahasiswa, 250);
        });

        nimInput.addEventListener('change', lookupMahasiswa);
        nimInput.addEventListener('blur', lookupMahasiswa);
    }

    tingkatSelect.addEventListener('change', function () {
        const tingkat = this.value;
        setSelectOptionsByTingkat(jenisPelanggaranSelect, allJenisOptions, tingkat, 'Pilih Jenis Pelanggaran');
        setSelectOptionsByTingkat(sanksiSelect, allSanksiOptions, tingkat, 'Pilih Sanksi');
        toggleTugasKhusus(tingkat);
        applySelectEnhancement();
    });

    if (penanggungTugasSelect) {
        penanggungTugasSelect.addEventListener('change', () => {
            const normalizedTingkat = String(tingkatSelect.value || '').trim().toUpperCase();
            const requiresTugasKhusus = ['I', 'II', 'III', '1', '2', '3'].includes(normalizedTingkat);
            syncTaskResponsibilityState(requiresTugasKhusus);
        });
    }

    setSelectOptionsByTingkat(jenisPelanggaranSelect, allJenisOptions, tingkatSelect.value, 'Pilih Jenis Pelanggaran');
    setSelectOptionsByTingkat(sanksiSelect, allSanksiOptions, tingkatSelect.value, 'Pilih Sanksi');
    toggleTugasKhusus(tingkatSelect.value);
    applySelectEnhancement();

    window.addEventListener('disciplink:select2-ready', applySelectEnhancement);

    if (nimInput && nimInput.value.trim() !== '' && form && form.getAttribute('action')) {
        lookupMahasiswa();
    }
})();

function showConfirmation() {
    const confirmAction = confirm('Apakah Anda yakin ingin melaporkan?');
    if (confirmAction) {
        window.location.href = '/';
    }
}
