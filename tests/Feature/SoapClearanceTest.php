<?php

namespace Tests\Feature;

use App\Models\Buku;
use App\Models\Kategori;
use App\Models\Peminjaman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoapClearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_wsdl_endpoint_returns_valid_xml_contract(): void
    {
        $response = $this->get('/soap/sipus.wsdl');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/xml', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString('<definitions', $response->getContent());
        $this->assertStringContainsString('CheckClearanceStatusRequest', $response->getContent());
        $this->assertStringContainsString('CheckClearanceStatusResponse', $response->getContent());
        $this->assertStringContainsString('SIPUSClearanceService', $response->getContent());
    }

    public function test_soap_endpoint_returns_cleared_true_when_no_active_loans(): void
    {
        $soapEnvelope = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:sip="http://sipus.del.ac.id/soap">
   <soapenv:Header/>
   <soapenv:Body>
      <sip:CheckClearanceStatusRequest>
         <sip:nim_peminjam>12S24054</sip:nim_peminjam>
      </sip:CheckClearanceStatusRequest>
   </soapenv:Body>
</soapenv:Envelope>
XML;

        $response = $this->call('POST', '/soap/sipus', [], [], [], [
            'CONTENT_TYPE' => 'text/xml; charset=utf-8',
        ], $soapEnvelope);

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString('<is_cleared>true</is_cleared>', $content);
        $this->assertStringContainsString('<active_loans>0</active_loans>', $content);
        $this->assertStringContainsString('<nim_peminjam>12S24054</nim_peminjam>', $content);
    }

    public function test_soap_endpoint_returns_cleared_false_when_active_loans_exist(): void
    {
        $kategori = Kategori::create(['kode_kategori' => 'IF', 'nama_kategori' => 'Informatika']);
        $buku = Buku::create([
            'isbn' => '9786020000011',
            'judul' => 'Struktur Data C++',
            'penulis' => 'Dosen Del',
            'penerbit' => 'Del Press',
            'tahun_terbit' => 2024,
            'kategori_id' => $kategori->id,
            'stok' => 2,
        ]);

        Peminjaman::create([
            'kode_pinjam' => 'PJM-SOAP001',
            'buku_id' => $buku->id,
            'nim_peminjam' => '12S24054',
            'nama_peminjam' => 'Rahel Silaban',
            'tanggal_pinjam' => today(),
            'tanggal_jatuh_tempo' => today()->addDays(7),
            'status' => 'dipinjam',
        ]);

        $soapEnvelope = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
   <SOAP-ENV:Body>
      <CheckClearanceStatusRequest>
         <nim_peminjam>12S24054</nim_peminjam>
      </CheckClearanceStatusRequest>
   </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
XML;

        $response = $this->call('POST', '/soap/sipus', [], [], [], [
            'CONTENT_TYPE' => 'text/xml',
        ], $soapEnvelope);

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString('<is_cleared>false</is_cleared>', $content);
        $this->assertStringContainsString('<active_loans>1</active_loans>', $content);
        $this->assertStringContainsString('Rahel Silaban', $content);
    }

    public function test_soap_endpoint_returns_fault_on_empty_nim(): void
    {
        $soapEnvelope = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
   <SOAP-ENV:Body>
      <CheckClearanceStatusRequest>
         <nim_peminjam></nim_peminjam>
      </CheckClearanceStatusRequest>
   </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
XML;

        $response = $this->call('POST', '/soap/sipus', [], [], [], [
            'CONTENT_TYPE' => 'text/xml',
        ], $soapEnvelope);

        $response->assertStatus(500);
        $content = $response->getContent();
        $this->assertStringContainsString('<faultcode>SOAP-ENV:Client</faultcode>', $content);
        $this->assertStringContainsString('Parameter nim_peminjam wajib disertakan', $content);
    }

    public function test_soap_endpoint_returns_fault_on_malformed_xml(): void
    {
        $malformedXml = '<SOAP-ENV:Envelope><SOAP-ENV:Body><BrokenTag></SOAP-ENV:Envelope>';

        $response = $this->call('POST', '/soap/sipus', [], [], [], [
            'CONTENT_TYPE' => 'text/xml',
        ], $malformedXml);

        $response->assertStatus(500);
        $content = $response->getContent();
        $this->assertStringContainsString('<faultcode>SOAP-ENV:Client</faultcode>', $content);
        $this->assertStringContainsString('Malformed XML', $content);
    }

    public function test_soap_endpoint_returns_fault_on_empty_body(): void
    {
        $response = $this->call('POST', '/soap/sipus', [], [], [], [
            'CONTENT_TYPE' => 'text/xml',
        ], '');

        $response->assertStatus(500);
        $content = $response->getContent();
        $this->assertStringContainsString('<faultcode>SOAP-ENV:Client</faultcode>', $content);
        $this->assertStringContainsString('XML request body tidak boleh kosong', $content);
    }
}

