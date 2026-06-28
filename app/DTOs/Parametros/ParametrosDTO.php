<?php

namespace App\DTOs\Parametros;

use App\Http\Requests\Parametros\UpdateParametrosRequest;
use Illuminate\Http\UploadedFile;

class ParametrosDTO
{
    public function __construct(
        public readonly string $legalName,
        public readonly string $rut,
        public readonly string $businessActivity,
        public readonly string $address,
        public readonly string $email,
        public readonly string $contactName,
        public readonly string $legalRepresentative,
        public readonly string $repRut,
        public readonly ?string $website,
        public readonly ?string $slogan,
        public readonly ?UploadedFile $logoFile
    ) {}

    public static function fromRequest(UpdateParametrosRequest $request): self
    {
        $logoFile = $request->file('company_logo_path');

        return new self(
            legalName: $request->validated('company_legal_name'),
            rut: $request->validated('company_rut'),
            businessActivity: $request->validated('company_business_activity'),
            address: $request->validated('company_address'),
            email: $request->validated('company_email'),
            contactName: $request->validated('company_contact_name'),
            legalRepresentative: $request->validated('company_legal_representative'),
            repRut: $request->validated('company_rep_rut'),
            website: $request->validated('company_website'),
            slogan: $request->validated('company_slogan'),
            logoFile: $logoFile
        );
    }

    public function toArray(): array
    {
        return [
            'company_legal_name'          => $this->legalName,
            'company_rut'                 => $this->rut,
            'company_business_activity'   => $this->businessActivity,
            'company_address'             => $this->address,
            'company_email'               => $this->email,
            'company_contact_name'        => $this->contactName,
            'company_legal_representative'=> $this->legalRepresentative,
            'company_rep_rut'             => $this->repRut,
            'company_website'             => $this->website,
            'company_slogan'              => $this->slogan,
        ];
    }
}