<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Services\VerificationService;
use GuzzleHttp\Client;
use App\Services\VerificationStrategies\RecipientVerification;
use App\Services\VerificationStrategies\IssuerVerification;
use App\Services\VerificationStrategies\SignatureVerification;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Accredify Verification API",
 *     version="1.0.0",
 *     description="API for verifying JSON files based on specific criteria."
 * )
 */
class VerificationController extends Controller
{
    protected VerificationService $verificationService;

    public function __construct(VerificationService $verificationService)
    {
        $this->verificationService = $verificationService;

        $this->verificationService->addStrategy(new RecipientVerification());
        $this->verificationService->addStrategy(new IssuerVerification(new Client()));
        $this->verificationService->addStrategy(new SignatureVerification());
    }

    /**
     * @OA\Post(
     *     path="/api/verify",
     *     summary="Verify a JSON file",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     description="The JSON file to be verified"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful verification",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="issuer", type="string", description="Name of the issuer"),
     *                 @OA\Property(property="result", type="string", description="Verification result")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid file format")
     * )
     */
    public function verify(Request $request): JsonResponse
    {
        $fileType = $request->file('file') ? 'file' : 'json';
        $fileContent = $this->getFileContent($request);

        if (!$fileContent) {
            return response()->json(['error' => 'No file uploaded or file exceeds size limit'], 400);
        }

        $data = json_decode($fileContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'Invalid JSON format'], 400);
        }

        $issuer = $data['data']['issuers'][0]['name'] ?? $data['data']['issuer']['name'];

        if (preg_match('/(?<=:string:).+$/', $issuer, $matches)) {
            $issuer = $matches[0];
        }

        $result = $this->verificationService->verify($data);

        if (!$result['success']) {
            $this->verificationService->storeVerificationResult(auth()->id(), $fileType, $result['result']);
            return response()->json([
                'data' => [
                    'issuer' => $issuer,
                    'result' => $result['result']
                ]
            ]);
        }

        $this->verificationService->storeVerificationResult(auth()->id(), $fileType, 'verified');

        return response()->json([
            'data' => [
                'issuer' => $issuer,
                'result' => 'verified'
            ]
        ]);
    }

    protected function getFileContent(Request $request)
    {
        if ($file = $request->file('file')) {
            $validator = Validator::make($request->all(), [
                'file' => 'max:2097152',
            ]);

            if ($validator->fails()) {
                return null;
            }

            return file_get_contents($file->getPathname());
        }

        return $request->getContent();
    }
}
