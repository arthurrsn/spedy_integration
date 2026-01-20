# 📑 Integração de Emissão de Nota Fiscal (Spedy API)

Este módulo foi desenvolvido como parte de um projeto **freelance** para automatizar o fluxo de faturamento de um sistema de pagamentos multigateway. O objetivo principal é a integração entre transações aprovadas e a API de Notas Fiscais de Serviço (NFSe) da **Spedy**.

---

## 🚀 O Desafio
O cliente precisava que, após a aprovação de uma transação por diferentes gateways (**Woovi, EFI Bank ou LunoxPay**), o sistema gerasse automaticamente o payload técnico e solicitasse a emissão da nota fiscal, garantindo a conformidade fiscal sem intervenção manual.

## 🛠️ Tecnologias Utilizadas
* **PHP**
* **cURL** (Requisições HTTP REST)
* **PDO - PHP Data Objects** (Persistência e segurança com Prepared Statements)
* **JSON** (Manipulação e formatação de dados)

## 🧠 Diferenciais Técnicos deste Código
* **Mapeamento de Dados:** Conversão de registros do banco de dados para o modelo de objetos complexo exigido pela API.
* **Segurança:** Uso de *Prepared Statements* para prevenir ataques de SQL Injection.
* **Logs e Monitoramento:** Implementação de `error_log` para rastreabilidade de falhas na API ou transações inexistentes.
* **Flexibilidade:** Suporte a múltiplos identificadores de transação (`tx_id`) em uma única lógica de busca.

---

## 📂 Estrutura das Funções

### 1. `jsonNotaFiscal($resultado)`
Responsável pela construção do payload. Garante que tipos de dados (como `float` para valores monetários) e strings formatadas em `UNICODE` estejam de acordo com o contrato da API.

### 2. `emitirNotaFiscal($tx_id)`
O motor da integração:
1. Consulta o banco de dados buscando transações com status **'Aprovado'**.
2. Prepara o cabeçalho HTTP com autenticação via `X-Api-Key`.
3. Dispara a requisição `POST` via cURL.
4. Valida o `httpCode` de retorno (200/201) para confirmar a emissão.

---
