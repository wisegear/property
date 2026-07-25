<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EpcCertificateResource;
use App\Services\EpcCertificateFinder;

class ScottishEpcCertificateController extends Controller
{
    public function __invoke(string $reference, EpcCertificateFinder $finder): EpcCertificateResource
    {
        $certificate = $finder->findScotland($reference);

        abort_if($certificate === null, 404, 'EPC certificate not found.');

        $certificate->_epc_nation = 'scotland';

        return new EpcCertificateResource($certificate);
    }
}
