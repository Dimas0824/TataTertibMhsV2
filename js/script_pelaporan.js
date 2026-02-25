(function () {
    const tingkatSelect = document.getElementById('tingkat');
    const jenisPelanggaranSelect = document.getElementById('jenisPelanggaran');
    const sanksiSelect = document.getElementById('sanksi');
    const deskripsiTugasContainer = document.getElementById('deskripsiTugas-container');
    const skipTugasContainer = document.getElementById('skipTugasKhusus-container');
    const skipTugasButton = document.getElementById('skipTugasKhusus');

    if (!tingkatSelect || !jenisPelanggaranSelect || !sanksiSelect) {
        return;
    }

    const filterOptionsByTingkat = (selectElement, tingkat) => {
        const options = selectElement.options;
        for (let i = 0; i < options.length; i++) {
            const option = options[i];
            const optionTingkat = option.getAttribute('data-tingkat');
            if (option.value === '' || optionTingkat === tingkat) {
                option.style.display = 'block';
            } else {
                option.style.display = 'none';
            }
        }
        selectElement.value = '';
    };

    const toggleTugasKhusus = (tingkat) => {
        if (!deskripsiTugasContainer || !skipTugasContainer) {
            return;
        }

        if (tingkat === 'I' || tingkat === 'II' || tingkat === 'III') {
            deskripsiTugasContainer.style.display = 'block';
            skipTugasContainer.style.display = 'block';
        } else {
            deskripsiTugasContainer.style.display = 'none';
            skipTugasContainer.style.display = 'none';
        }
    };

    tingkatSelect.addEventListener('change', function () {
        const tingkat = this.value;
        filterOptionsByTingkat(jenisPelanggaranSelect, tingkat);
        filterOptionsByTingkat(sanksiSelect, tingkat);
        toggleTugasKhusus(tingkat);
    });

    if (skipTugasButton && deskripsiTugasContainer) {
        skipTugasButton.addEventListener('click', function () {
            if (confirm('Apakah Anda yakin ingin melaporkan ke DPA dan tidak mengisi tugas khusus?')) {
                deskripsiTugasContainer.style.display = 'none';
            }
        });
    }
})();

function showConfirmation() {
    const confirmAction = confirm('Apakah Anda yakin ingin melaporkan?');
    if (confirmAction) {
        window.location.href = 'pelanggaran_dosen.php';
    }
}
