<?php

namespace App\Http\Controllers\Soap;

use App\Http\Controllers\Controller;
use App\Models\Peminjaman;
use DOMDocument;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SimpleXMLElement;

class LibrarySoapController extends Controller
{
    /**
     * Menampilkan dokumen kontrak WSDL untuk SIPUS Bebas Pustaka.
     */
    public function wsdl(Request $request): Response
    {
        $endpoint = url('/soap/sipus');
        $wsdl = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<definitions name="SIPUSClearanceService"
    targetNamespace="http://sipus.del.ac.id/soap"
    xmlns:tns="http://sipus.del.ac.id/soap"
    xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns="http://schemas.xmlsoap.org/wsdl/">

    <types>
        <xsd:schema targetNamespace="http://sipus.del.ac.id/soap" elementFormDefault="qualified">
            <xsd:element name="CheckClearanceStatusRequest">
                <xsd:complexType>
                    <xsd:sequence>
                        <xsd:element name="nim_peminjam" type="xsd:string"/>
                    </xsd:sequence>
                </xsd:complexType>
            </xsd:element>

            <xsd:element name="CheckClearanceStatusResponse">
                <xsd:complexType>
                    <xsd:sequence>
                        <xsd:element name="nim_peminjam" type="xsd:string"/>
                        <xsd:element name="nama_peminjam" type="xsd:string"/>
                        <xsd:element name="active_loans" type="xsd:int"/>
                        <xsd:element name="is_cleared" type="xsd:boolean"/>
                        <xsd:element name="message" type="xsd:string"/>
                    </xsd:sequence>
                </xsd:complexType>
            </xsd:element>
        </xsd:schema>
    </types>

    <message name="CheckClearanceStatusInput">
        <part name="parameters" element="tns:CheckClearanceStatusRequest"/>
    </message>

    <message name="CheckClearanceStatusOutput">
        <part name="parameters" element="tns:CheckClearanceStatusResponse"/>
    </message>

    <portType name="SIPUSClearancePortType">
        <operation name="CheckClearanceStatus">
            <documentation>Memeriksa kelayakan bebas pustaka mahasiswa berdasarkan NIM.</documentation>
            <input message="tns:CheckClearanceStatusInput"/>
            <output message="tns:CheckClearanceStatusOutput"/>
        </operation>
    </portType>

    <binding name="SIPUSClearanceBinding" type="tns:SIPUSClearancePortType">
        <soap:binding style="document" transport="http://schemas.xmlsoap.org/soap/http"/>
        <operation name="CheckClearanceStatus">
            <soap:operation soapAction="http://sipus.del.ac.id/soap#CheckClearanceStatus"/>
            <input>
                <soap:body use="literal"/>
            </input>
            <output>
                <soap:body use="literal"/>
            </output>
        </operation>
    </binding>

    <service name="SIPUSClearanceService">
        <documentation>Layanan SOAP Bebas Pustaka Perpustakaan Institut Teknologi Del</documentation>
        <port name="SIPUSClearancePort" binding="tns:SIPUSClearanceBinding">
            <soap:address location="{$endpoint}"/>
        </port>
    </service>
</definitions>
XML;

        return response($wsdl, 200, [
            'Content-Type' => 'text/xml; charset=utf-8',
        ]);
    }

    /**
     * Memproses pesan SOAP Envelope untuk operasi CheckClearanceStatus.
     */
    public function handle(Request $request): Response
    {
        $rawXml = trim($request->getContent());

        if (empty($rawXml)) {
            return $this->soapFault('SOAP-ENV:Client', 'XML request body tidak boleh kosong.');
        }

        // Validasi struktur XML menggunakan DOMDocument untuk mendeteksi XML yang cacat
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $prevEntityLoader = libxml_disable_entity_loader(true);
        $prevInternalErrors = libxml_use_internal_errors(true);

        $loaded = $dom->loadXML($rawXml, LIBXML_NONET);

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prevInternalErrors);
        if (function_exists('libxml_disable_entity_loader')) {
            libxml_disable_entity_loader($prevEntityLoader);
        }

        if (! $loaded || count($errors) > 0) {
            return $this->soapFault('SOAP-ENV:Client', 'Format XML request cacat atau tidak valid (Malformed XML).');
        }

        // Ekstraksi parameter nim_peminjam
        $nim = null;
        $nodes = $dom->getElementsByTagName('nim_peminjam');
        if ($nodes->length > 0) {
            $nim = trim($nodes->item(0)->textContent);
        } else {
            // Coba alternatif tag 'nim'
            $altNodes = $dom->getElementsByTagName('nim');
            if ($altNodes->length > 0) {
                $nim = trim($altNodes->item(0)->textContent);
            }
        }

        if (empty($nim)) {
            return $this->soapFault('SOAP-ENV:Client', 'Parameter nim_peminjam wajib disertakan dan tidak boleh kosong.');
        }

        $nimNormalized = strtoupper($nim);

        try {
            // Hitung pinjaman aktif untuk NIM tersebut
            $activeLoans = Peminjaman::where('nim_peminjam', $nimNormalized)
                ->where('status', 'dipinjam')
                ->count();

            // Ambil nama peminjam dari riwayat terakhir jika ada
            $latestLoan = Peminjaman::where('nim_peminjam', $nimNormalized)
                ->latest('id')
                ->first();
            $namaPeminjam = $latestLoan ? $latestLoan->nama_peminjam : 'Mahasiswa Del';

            $isCleared = ($activeLoans === 0);
            $isClearedStr = $isCleared ? 'true' : 'false';
            $message = $isCleared
                ? 'Mahasiswa dinyatakan bebas pustaka (tidak ada pinjaman aktif).'
                : "Mahasiswa masih memiliki {$activeLoans} pinjaman buku aktif. Bebas pustaka ditolak.";

            $responseXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tns="http://sipus.del.ac.id/soap">
    <SOAP-ENV:Body>
        <tns:CheckClearanceStatusResponse>
            <nim_peminjam>{$nimNormalized}</nim_peminjam>
            <nama_peminjam>{$namaPeminjam}</nama_peminjam>
            <active_loans>{$activeLoans}</active_loans>
            <is_cleared>{$isClearedStr}</is_cleared>
            <message>{$message}</message>
        </tns:CheckClearanceStatusResponse>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
XML;

            return response($responseXml, 200, [
                'Content-Type' => 'text/xml; charset=utf-8',
            ]);
        } catch (Exception $e) {
            return $this->soapFault('SOAP-ENV:Server', 'Terjadi kesalahan internal pada server SOAP: '.$e->getMessage(), 500);
        }
    }

    /**
     * Membentuk respons SOAP Fault.
     */
    protected function soapFault(string $code, string $message, int $httpStatus = 500): Response
    {
        $escapedMessage = htmlspecialchars($message, ENT_XML1, 'UTF-8');
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
    <SOAP-ENV:Body>
        <SOAP-ENV:Fault>
            <faultcode>{$code}</faultcode>
            <faultstring>{$escapedMessage}</faultstring>
        </SOAP-ENV:Fault>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
XML;

        return response($xml, $httpStatus, [
            'Content-Type' => 'text/xml; charset=utf-8',
        ]);
    }
}
