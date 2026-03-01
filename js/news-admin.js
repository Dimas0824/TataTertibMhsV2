
const addBeritaButton = document.getElementById('addButton'); 
const editBeritaButtons = document.querySelectorAll('.edit-button'); 
const insertBeritaModal = document.getElementById('insertBeritaModal'); 
const editBeritaModal = document.getElementById('editBeritaModal'); 
const closeButtons = document.querySelectorAll('.close'); 


addBeritaButton.addEventListener('click', () => {
    insertBeritaModal.style.display = 'block';
});

// Fungsi untuk membuka modal edit berita
editBeritaButtons.forEach((button) => {
    button.addEventListener('click', (e) => {
        const newsId = button.getAttribute('data-id');
        const judul = button.getAttribute('data-judul');
        const konten = button.getAttribute('data-konten');

        // Populate the edit modal
        document.getElementById('editNewsId').value = newsId;
        document.getElementById('editJudul').value = judul;
        document.getElementById('editKonten').value = konten;

        editBeritaModal.style.display = 'block';
    });
});

closeButtons.forEach((closeButton) => {
    closeButton.addEventListener('click', () => {
        insertBeritaModal.style.display = 'none';
        editBeritaModal.style.display = 'none';
    });
});


window.addEventListener('click', (event) => {
    if (event.target === insertBeritaModal) {
        insertBeritaModal.style.display = 'none';
    }
    if (event.target === editBeritaModal) {
        editBeritaModal.style.display = 'none';
    }
});
