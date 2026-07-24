<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EpcCertificateResource;
use App\Services\EpcCertificateFinder;

class EpcCertificateController extends Controller
{
    public function __invoke(string $reference, EpcCertificateFinder $finder): EpcCertificateResource
    {
        $certificate = $finder->findEnglandWales($reference);

        abort_if($certificate === null, 404, 'EPC certificate not found.');

        return new EpcCertificateResource($certificate);
    }
}
