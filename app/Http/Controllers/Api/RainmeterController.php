<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RainmeterStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RainmeterController extends Controller
{
    public function __construct(private readonly RainmeterStatusService $status)
    {
    }

    /**
     * Read-only plain-text status for the Rainmeter skin. The body format is a
     * contract; see RainmeterStatusService.
     */
    public function __invoke(Request $request): Response
    {
        $body = $this->status->render($request->user());

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
