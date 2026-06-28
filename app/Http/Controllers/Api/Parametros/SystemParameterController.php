<?php

namespace App\Http\Controllers\Api\Parametros;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametros\UpdateParametrosRequest;
use App\DTOs\Parametros\ParametrosDTO;
use App\Services\Parametros\SystemParameterService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
// use Illuminate\Http\Request;

class SystemParameterController extends Controller
{
    // Inyección de dependencias por constructor de élite
    public function __construct(
        protected SystemParameterService $parameterService
    ) {}

    /**
     * Obtiene todos los parámetros guardados en el sistema.
     * Retorna un arreglo llave => valor para alimentar el formulario en React.
     */
    public function index(): JsonResponse
    {
        try {
            // Delegamos la consulta a la capa de servicio
            $parameters = $this->parameterService->getAllParameters();

            return response()->json([
                'status' => true,
                'data'   => $parameters
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error crítico al recuperar los parámetros del sistema.',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Actualiza de forma masiva los parámetros generales del sistema.
     */
    public function updateBulk(UpdateParametrosRequest $request): JsonResponse
    {
        try {
            // Transformar la petición validada en un DTO inmutable
            $dto = ParametrosDTO::fromRequest($request);

            // Ejecutar la lógica de negocio a través del servicio dedicado
            $this->parameterService->updateBulkParameters($dto);

            return response()->json([
                'status'  => true,
                'message' => 'Parámetros del sistema actualizados con éxito.'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error crítico al procesar la actualización de configuración.',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}