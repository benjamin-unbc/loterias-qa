// Protección mejorada contra spam de Enter para PlaysManager
document.addEventListener('DOMContentLoaded', function() {
    let enterThrottled = false;
    let enterTimeout;
    let lastEnterTime = 0;
    const THROTTLE_DELAY = 1500; // Aumentado a 1.5 segundos
    const MIN_ENTER_INTERVAL = 800; // Mínimo 800ms entre Enter válidos

    // Protección global para todos los inputs del formulario
    const formInputs = ['number', 'position', 'import', 'numberR', 'positionR'];
    
    formInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    const currentTime = Date.now();
                    
                    // Verificar si ha pasado suficiente tiempo desde el último Enter
                    if (currentTime - lastEnterTime < MIN_ENTER_INTERVAL) {
                        e.preventDefault();
                        console.log('Enter bloqueado por throttling');
                        return;
                    }
                    
                    // Si ya está throttled, no hacer nada
                    if (enterThrottled) {
                        e.preventDefault();
                        console.log('Enter bloqueado por throttling activo');
                        return;
                    }

                    // Marcar como throttled y actualizar tiempo
                    enterThrottled = true;
                    lastEnterTime = currentTime;
                    
                    // Limpiar timeout anterior si existe
                    if (enterTimeout) {
                        clearTimeout(enterTimeout);
                    }

                    // Resetear después del delay configurado
                    enterTimeout = setTimeout(() => {
                        enterThrottled = false;
                        console.log('Throttling resetado');
                    }, THROTTLE_DELAY);
                }
            });
        }
    });
});
