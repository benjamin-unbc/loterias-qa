<div id="real-time-clock" class="flex gap-2 items-center justify-center bg-gray-700 text-yellow-200 w-full px-3 py-1 rounded-md text-xs md:text-sm">
    <i class="fa-solid fa-clock"></i> <span id="clock-time">{{ now()->setTimezone('America/Argentina/Buenos_Aires')->format('H:i:s') }}</span>
</div>

<script>
    // Función para actualizar la hora en tiempo real
    function updateClock() {
        const timezone = 'America/Argentina/Buenos_Aires';
        const clockElement = document.getElementById('clock-time');

        if (clockElement) {
            const now = new Date();
            const options = { timeZone: timezone, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
            const formattedTime = new Intl.DateTimeFormat('es-AR', options).format(now);
            clockElement.textContent = formattedTime;
        }
    }

    // Actualizar la hora inmediatamente al cargar la página
    updateClock();

    // Actualizar la hora cada segundo
    setInterval(updateClock, 1000);
</script>
