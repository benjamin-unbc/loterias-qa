<?php

if (!function_exists('banner_message')) {

    /**
     * Alert banner message flash
     * @param string $message
     * @param string $style
     */
    function banner_message(string $message, string $style = 'success')
    {
        request()->session()->put('flash.banner', $message);
        request()->session()->put('flash.bannerStyle', $style);
    }
        function displayUltraBoldTicket(string $ticketNumber): string
    {
        // Usamos htmlspecialchars para prevenir XSS si el número de ticket
        // pudiera venir de una fuente no confiable, aunque generalmente son generados.
        return '<span class="ticket-ultranegrita fw-bold" style="font-weight: 900;">' . htmlspecialchars($ticketNumber) . '</span>';

    }
}
