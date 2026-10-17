<?php

namespace App\Console\Commands;

use App\Services\SimuladorService;
use Illuminate\Console\Command;

/**
 * Simulación de la operación para la demostración del mapa: php artisan transmetro:simular --buses=6
 * Corre en bucle hasta Ctrl + C; al detenerse finaliza los recorridos que inició.
 */
class SimularTransmetro extends Command
{
    protected $signature = 'transmetro:simular
        {--buses=6 : Cantidad de buses que el simulador mantiene en ruta}
        {--intervalo=3 : Segundos entre cada avance}
        {--ciclos= : Detenerse después de esta cantidad de avances (útil en pruebas)}';

    protected $description = 'Simula buses en ruta, llegadas, pasajeros y alertas para el seguimiento en vivo';

    private bool $seguir = true;

    public function handle(SimuladorService $simulador): int
    {
        $this->escucharCtrlC();

        $buses = max(1, (int) $this->option('buses'));
        $intervalo = max(0.0, (float) $this->option('intervalo'));
        $ciclos = $this->option('ciclos') !== null ? (int) $this->option('ciclos') : null;

        $estado = $simulador->iniciar($buses);
        $this->info("Simulación iniciada con {$buses} buses (Ctrl + C para detener).");

        for ($ciclo = 1; $this->seguir && ($ciclos === null || $ciclo <= $ciclos); $ciclo++) {
            if ($ciclo > 1) {
                $estado = $simulador->avanzar();
            }
            $this->line(sprintf('[%s] %d recorridos del simulador en ruta', now()->format('H:i:s'), count($estado['propios'])));

            // Espera en pasos cortos para responder rápido a Ctrl + C.
            for ($espera = 0.0; $this->seguir && $espera < $intervalo && ($ciclos === null || $ciclo < $ciclos); $espera += 0.1) {
                usleep(100_000);
            }
        }

        $finalizados = $simulador->detener();
        $this->info("Simulación detenida: {$finalizados} recorridos finalizados y sus buses quedaron disponibles.");

        return self::SUCCESS;
    }

    /**
     * Ctrl + C detiene el bucle para poder finalizar los recorridos (en Windows y en Linux/macOS).
     */
    private function escucharCtrlC(): void
    {
        if (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler(function (): void {
                $this->seguir = false;
            });
        } elseif (extension_loaded('pcntl')) {
            $this->trap([SIGINT, SIGTERM], function (): void {
                $this->seguir = false;
            });
        }
    }
}
