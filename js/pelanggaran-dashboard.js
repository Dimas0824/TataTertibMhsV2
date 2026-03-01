document.addEventListener("DOMContentLoaded", () => {
    const showFeedback = (type, message) => {
        if (window.AppModal && typeof window.AppModal.show === "function") {
            window.AppModal.show({ type, message });
            return;
        }
        alert(message);
    };

    const modal = document.getElementById("uploadModal");
    const closeModalBtn = document.querySelector(".btn-secondary"); // Tombol Batal
    const saveBtn = document.querySelector(".btn-primary"); // Tombol Simpan

    // Fungsi untuk membuka modal
    function openModal() {
        modal.style.display = "flex";
    }

    // Fungsi untuk menutup modal
    function closeModal() {
        modal.style.display = "none";
    }

    // Tambahkan event listener pada tombol Batal
    closeModalBtn.addEventListener("click", closeModal);

    // Validasi file saat tombol Simpan diklik
    saveBtn.addEventListener("click", (e) => {
        e.preventDefault();

        const suratPernyataanInput = document.getElementById("suratPernyataan");
        const tugasKhususInput = document.getElementById("tugasKhusus");

        const isSuratPernyataanValid = validateFile(suratPernyataanInput);
        const isTugasKhususValid = validateFile(tugasKhususInput);

        if ((isSuratPernyataanValid || isTugasKhususValid)||(isSuratPernyataanValid && isTugasKhususValid)) {
            showFeedback("success", "File berhasil diunggah!");
            // Reset kedua form setelah berhasil
            suratPernyataanInput.value = "";
            tugasKhususInput.value = "";
            closeModal();
        } else {
            showFeedback("error", "Gagal mengunggah file. Pastikan file sudah dipilih dan ukurannya tidak lebih dari 2 MB.");
        }
    });

    // Fungsi validasi file
    function validateFile(inputElement) {
        const file = inputElement.files[0];
        if (!file) {
            showFeedback("error", `File ${inputElement.name} tidak boleh kosong.`);
            return false;
        }

        const maxSize = 2 * 1024 * 1024; // 2 MB
        if (file.size > maxSize) {
            showFeedback("error", `File ${inputElement.name} terlalu besar! Maksimal ukuran file adalah 2 MB.`);
            return false;
        }

        return true;
    }

    // Event listener opsional untuk membuka modal
    document.querySelector("#openModalButton")?.addEventListener("click", openModal);
});
