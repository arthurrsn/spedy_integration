<?php

/**
 * Arquivo responsável pela integração com a API de Notas Fiscais do Spedy.
 */

require_once __DIR__ . '/../config/config_db.php';

/**
 * Gera o payload JSON formatado para a API do Spedy.
 *
 * @param array $resultado Dados da transação vindos do banco de dados.
 * @return string JSON formatado em UNICODE.
 */
function jsonNotaFiscal($resultado) {

    $payload = [
        "integrationId"         => $resultado['id'], // ID de controle do sistema
        "issuedOn"              => date("Y-m-d\TH:i:s\Z"),
        "effectiveDate"         => date("Y-m-d\TH:i:s\Z"),
        
        // Dados do Cliente
        "receiver" => [
            "name"             => trim($resultado['nome'] . " " . $resultado['sobrenome']),
            "federalTaxNumber" => null,
            "stateTaxNumber"   => null,
            "cityTaxNumber"    => null,
            "email"            => $resultado['email'],
            "phoneNumber"      => $resultado['telefone'],
            "address" => [
                "street"                => null,
                "district"              => null,
                "postalCode"            => null,
                "number"                => null,
                "additionalInformation" => null,
                "city" => [
                    "name"  => null,
                    "state" => null,
                    "code"  => null
                ],
                "country" => "BRA"
            ]
        ],
    
        "number"                => $resultado['id'],
        "status"                => "created",
        "additionalInformation" => null,
        "sendEmailToCustomer"   => true, // Habilita envio do email ao cliente
        "description"           => $resultado['descricao'],
        "batchNumber"           => 0,
        "rpsNumber"             => 0,
        "rpsSeries"             => 0,
        
        // Códigos de Serviço
        "cnaeCode"              => null,
        "nbsCode"               => null,
        "federalServiceCode"    => null,
        "nationalTaxationCode"  => null,
        "cityServiceCode"       => null,
        "taxationType"          => null,
        "cstPisCofins"          => null,
    
        // Valores e Impostos (Chave 'total' no singular conforme novo modelo)
        "total" => [
            "invoiceAmount"               => (float)$resultado['valor'],
            "netAmount"                   => (float)$resultado['valor'],
            "issBaseTax"                  => 0.0,
            "irRate"                      => 0.0,
            "csllRate"                    => 0.0,
            "pisRate"                     => 0.0,
            "cofinsRate"                  => 0.0,
            "inssRate"                    => 0.0,
            "issRate"                     => 0.0,
            "issAmount"                   => 0.0,
            "discountUnconditionedAmount" => 0.0,
            "discountConditionedAmount"   => 0.0,
            "irAmount"                    => 0.0,
            "pisAmount"                   => 0.0,
            "cofinsAmount"                => 0.0,
            "inssAmount"                  => 0.0,
            "csllAmount"                  => 0.0,
            "othersAmount"                => 0.0,
            "deductionsAmount"            => 0.0,
            "irWithheld"                  => false,
            "issWithheld"                 => false,
            "cofinsWithheld"              => false,
            "inssWithheld"                => false,
            "csllWithheld"                => false,
            "pisWithheld"                 => false,
            "ibsStateRate"                => 0.0,
            "ibsCityRate"                 => 0.0,
            "cbsRate"                     => 0.0,
            "ibsCbsBaseTax"               => 0.0,
            "ibsStateAmount"              => 0.0,
            "ibsCityAmount"               => 0.0,
            "ibsAmount"                   => 0.0,
            "cbsAmount"                   => 0.0
        ],
    
        // Local de Prestação
        "location" => [
            "code"  => null,
            "name"  => null,
            "state" => null
        ],
    
        // Reforma Tributária
        "ibsCbs" => [
            "cst"                    => 0,
            "classification"         => 0,
            "operationIndicatorCode" => null,
            "isPersonalUse"          => true
        ],
    
        // Benefício Municipal
        "national" => [
            "municipalBenefit" => [
                "type"           => null,
                "identification" => null
            ]
        ]
    ];

    return json_encode($payload, JSON_UNESCAPED_UNICODE);
}

/**
 * Busca uma transação aprovada e solicita a emissão da Nota Fiscal ao Spedy.
 *
 * @param string $tx_id Identificador da transação (Woovi, EFI ou Lunox).
 * @return array|null Retorna os dados da transação ou null em caso de falha.
 */
function emitirNotaFiscal($tx_id) {
    global $pdo;
    
    $url   = "https://api.spedy.com.br/v1/service-invoices"; 
    $token = "TOKEN";

    try {
        // Busca os dados da transação no banco de dados
        $sql = "SELECT id, nome, sobrenome, cpf, email, telefone, descricao, valor 
                FROM transacoes 
                WHERE (woovi_tx_id = :id1 OR efibank_tx_id = :id2 OR lunoxpay_tx_id = :id3) 
                AND status = 'Aprovado' 
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id1' => $tx_id,
            'id2' => $tx_id,
            'id3' => $tx_id
        ]);
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resultado) {
            error_log("Spedy: Transação não encontrada ou não aprovada para o ID: $tx_id");
            return null;
        }

        // Prepara o JSON para envio
        $jsonFinal = jsonNotaFiscal($resultado);
        
        // Inicia a requisição via cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonFinal);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Api-Key: $token",
            "Content-Type: application/json"
        ]);

        $resposta = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Validação da resposta da API
        if ($httpCode == 201 || $httpCode == 200) {
            // Sucesso: Registra internamente no log do servidor
            error_log("Spedy: Nota Fiscal emitida com sucesso para o ID: $tx_id");
        } else {
            // Falha: Registra o erro técnico para análise do desenvolvedor
            error_log("Spedy Erro ($httpCode): Resposta da API: " . $resposta);
        }
        
        return $resultado;

    } catch (PDOException $e) {
        error_log("Erro de Banco de Dados: " . $e->getMessage());
        return null;
    }
}
