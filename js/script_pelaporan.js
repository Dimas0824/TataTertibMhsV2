(function () {
    const form = document.getElementById('pelanggaranForm');
    const nimInput = document.getElementById('nim');
    const namaInput = document.getElementById('nama');
    const semesterInput = document.getElementById('semester');
    const prodiInput = document.getElementById('prodi');
    const nimHelpText = document.getElementById('nimHelpText');
    const tingkatSelect = document.getElementById('tingkat');
    const jenisPelanggaranSelect = document.getElementById('jenisPelanggaran');
    const sanksiSelect = document.getElementById('sanksi');
    const deskripsiTugasContainer = document.getElementById('deskripsiTugas-container');

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

    const toggleTugasKhusus = (tingkat) => {
        if (!deskripsiTugasContainer) {
            return;
        }

        if (tingkat === 'I' || tingkat === 'II' || tingkat === 'III') {
            deskripsiTugasContainer.style.display = 'block';
        } else {
            deskripsiTugasContainer.style.display = 'none';
        }
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

    let lookupRequestId = 0;
    const lookupEndpoint = '../Request/Handler_Pelaporan.php?action=lookup_mahasiswa';

    const lookupMahasiswa = async () => {
        if (!nimInput) {
            return;
        }

        const nim = nimInput.value.trim();
        const currentRequestId = ++lookupRequestId;

        if (nim === '') {
            clearIdentity('Isi NIM, identitas mahasiswa akan terisi otomatis.');
            return;
        }

        if (nimHelpText) {
            nimHelpText.textContent = 'Mencari data mahasiswa...';
        }

        try {
            const response = await fetch(`${lookupEndpoint}&nim=${encodeURIComponent(nim)}`, {
                method: 'GET',
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json();

            if (currentRequestId !== lookupRequestId) {
                return;
            }

            if (!response.ok || !payload.success) {
                clearIdentity(payload.message || 'Mahasiswa tidak ditemukan.');
                return;
            }

            const mahasiswa = payload.data || {};
            if (namaInput) namaInput.value = mahasiswa.nama_lengkap || '';
            if (prodiInput) prodiInput.value = mahasiswa.nama_prodi || '';
            if (semesterInput) semesterInput.value = calculateSemester(mahasiswa.angkatan);
            if (nimHelpText) nimHelpText.textContent = 'Data mahasiswa ditemukan.';
        } catch (_error) {
            if (currentRequestId !== lookupRequestId) {
                return;
            }
            clearIdentity('Gagal mengambil data mahasiswa.');
        }
    };

    let nimDebounceTimer = null;
    if (nimInput) {
        nimInput.addEventListener('input', () => {
            if (nimDebounceTimer) {
                clearTimeout(nimDebounceTimer);
            }
            nimDebounceTimer = setTimeout(lookupMahasiswa, 350);
        });
        nimInput.addEventListener('blur', lookupMahasiswa);
    }

    tingkatSelect.addEventListener('change', function () {
        const tingkat = this.value;
        setSelectOptionsByTingkat(jenisPelanggaranSelect, allJenisOptions, tingkat, 'Pilih Jenis Pelanggaran');
        setSelectOptionsByTingkat(sanksiSelect, allSanksiOptions, tingkat, 'Pilih Sanksi');
        toggleTugasKhusus(tingkat);
        refreshSelect2(jenisPelanggaranSelect, 'Cari jenis pelanggaran');
        if (sanksiSelect) {
            refreshSelect2(sanksiSelect, 'Pilih sanksi');
        }
    });

    setSelectOptionsByTingkat(jenisPelanggaranSelect, allJenisOptions, tingkatSelect.value, 'Pilih Jenis Pelanggaran');
    setSelectOptionsByTingkat(sanksiSelect, allSanksiOptions, tingkatSelect.value, 'Pilih Sanksi');
    refreshSelect2(jenisPelanggaranSelect, 'Cari jenis pelanggaran');
    if (sanksiSelect) {
        refreshSelect2(sanksiSelect, 'Pilih sanksi');
    }

    if (nimInput && nimInput.value.trim() !== '' && form && form.getAttribute('action')) {
        lookupMahasiswa();
    }
})();

function showConfirmation() {
    const confirmAction = confirm('Apakah Anda yakin ingin melaporkan?');
    if (confirmAction) {
        window.location.href = 'pelanggaran_dosen.php';
    }
}
