<div>
    <div id="captura" class="p-6 bg-gray-100 border rounded shadow-md">
        <h2 class="text-lg font-bold">Este es el contenido a capturar</h2>
        <p>Livewire + JavaScript funcionando juntos 🚀</p>
    </div>

    <button wire:click="descargarImagen" class="mt-4 bg-blue-500 text-white px-4 py-2 rounded">Descargar Imagen</button>
</div>

<script>
    window.addEventListener('descargar-imagen', event => {
        html2canvas(document.querySelector("#captura")).then(canvas => {
            let link = document.createElement('a');
            link.href = canvas.toDataURL("image/png"); // Convierte el div a imagen
            link.download = 'captura.png'; // Define el nombre del archivo
            link.click(); // Descarga la imagen
        });
    });
</script>