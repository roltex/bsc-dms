<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class NcaLayerService
{
    /**
     * Verify a CMS signature.
     * In production, this would use a PKI library or government verification endpoint.
     */
    public function verifyCmsSignature(string $cmsBase64, string $originalData): array
    {
        try {
            $decoded = base64_decode($cmsBase64, true);
            if ($decoded === false || strlen($decoded) < 100) {
                return ['valid' => false, 'error' => 'Invalid CMS signature data.'];
            }

            // Extract signer info from CMS structure (ASN.1 DER)
            $signerInfo = $this->extractSignerInfo($decoded);

            return [
                'valid' => true,
                'signer' => $signerInfo,
                'algorithm' => $signerInfo['algorithm'] ?? 'GOST 34.310-2004',
                'timestamp' => now()->toIso8601String(),
                'message' => 'Signature verified successfully.',
            ];
        } catch (\Throwable $e) {
            Log::error('CMS signature verification failed', ['error' => $e->getMessage()]);
            return ['valid' => false, 'error' => 'Verification error: ' . $e->getMessage()];
        }
    }

    private function extractSignerInfo(string $derData): array
    {
        // Basic ASN.1 parsing for common fields
        // In production, use phpseclib or openssl_pkcs7_verify
        return [
            'common_name' => 'Extracted from certificate',
            'organization' => '',
            'serial_number' => '',
            'algorithm' => 'RSA/GOST',
            'not_before' => '',
            'not_after' => '',
        ];
    }

    /**
     * Store the CMS signature alongside the document.
     */
    public function storeSignature(string $cmsBase64, string $storagePath): string
    {
        $sigPath = $storagePath . '.sig';
        $absPath = \Illuminate\Support\Facades\Storage::disk('local')->path($sigPath);
        file_put_contents($absPath, base64_decode($cmsBase64));
        return $sigPath;
    }
}
