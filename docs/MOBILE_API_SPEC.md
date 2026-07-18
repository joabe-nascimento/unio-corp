# Unio Mobile API v1.0

API REST para o aplicativo mobile de pacientes da Unio Saúde.

## 📱 Visão Geral

A Unio Mobile API permite que pacientes acessem seus dados, agendamentos, documentos e muito mais através de um aplicativo mobile (iOS/Android).

### Base URL
```
https://uniosaude.uniowork.com.br/api/mobile
```

### Autenticação
A API utiliza **JWT (JSON Web Tokens)** para autenticação. Após o login, inclua o token em todas as requisições protegidas:

```
Authorization: Bearer {seu_token_jwt}
```

---

## 🔐 Autenticação

### POST `/auth/login`
Autenticar paciente e obter token de acesso.

**Request:**
```json
{
  "cpf": "123.456.789-00",
  "password": "senha123"
}
```

ou

```json
{
  "email": "paciente@email.com",
  "password": "senha123"
}
```

**Response (200):**
```json
{
  "success": true,
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expires_in": 86400,
  "patient": {
    "id": 123,
    "nome": "João Silva",
    "cpf": "123.456.789-00",
    "email": "joao@email.com",
    "telefone": "(11) 98765-4321"
  }
}
```

---

### POST `/auth/register`
Cadastrar novo paciente.

**Request:**
```json
{
  "nome": "João Silva",
  "cpf": "123.456.789-00",
  "email": "joao@email.com",
  "telefone": "(11) 98765-4321",
  "data_nascimento": "1990-01-15",
  "password": "senha123",
  "password_confirmation": "senha123"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Cadastro realizado com sucesso",
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "patient_id": 124
}
```

---

### POST `/auth/forgot-password`
Recuperar senha esquecida.

**Request:**
```json
{
  "cpf": "123.456.789-00"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Link de recuperação enviado para seu telefone/email"
}
```

---

## 👤 Perfil do Paciente

### GET `/patient/profile`
Obter dados completos do perfil.

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "patient": {
    "id": 123,
    "codigo": "PAC-2026-123",
    "nome": "João Silva",
    "cpf": "123.456.789-00",
    "rg": "12.345.678-9",
    "email": "joao@email.com",
    "telefone": "(11) 98765-4321",
    "data_nascimento": "1990-01-15",
    "idade": 36,
    "sexo": "M",
    "foto_url": "https://...avatar.jpg",
    "endereco": {
      "cep": "01310-100",
      "logradouro": "Avenida Paulista",
      "numero": "1578",
      "complemento": "Apto 123",
      "bairro": "Bela Vista",
      "cidade": "São Paulo",
      "uf": "SP"
    },
    "plano": {
      "nome": "Essencial",
      "numero_carteirinha": "123456789",
      "validade": "2027-12-31"
    },
    "convenio": {
      "nome": "Unimed",
      "numero": "987654321"
    }
  }
}
```

---

## 📅 Agendamentos

### GET `/patient/appointments`
Listar agendamentos do paciente.

**Query Params:**
- `status` (opcional): `pendente`, `confirmado`, `realizado`, `cancelado`
- `periodo` (opcional): `proximos`, `passados`
- `limit` (opcional): quantidade de resultados (default: 50)

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "total": 5,
  "agendamentos": [
    {
      "id": 456,
      "data": "2026-07-20T14:30:00-03:00",
      "data_formatada": "20/07/2026 às 14:30",
      "profissional": {
        "nome": "Dr. João Médico",
        "especialidade": "Cardiologia",
        "crm": "123456"
      },
      "tipo": "Consulta",
      "local": {
        "nome": "Clínica Centro",
        "endereco": "Av. Paulista, 1000 - São Paulo/SP",
        "telefone": "(11) 3000-0000"
      },
      "status": "confirmado",
      "status_label": "Confirmado",
      "observacoes": "Trazer exames anteriores",
      "pode_cancelar": true,
      "pode_confirmar": false,
      "dias_para_consulta": 2
    }
  ]
}
```

---

### POST `/patient/confirm-appointment/{id}`
Confirmar um agendamento.

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Agendamento confirmado com sucesso",
  "agendamento": {
    "id": 456,
    "status": "confirmado"
  }
}
```

---

### POST `/patient/cancel-appointment/{id}`
Cancelar um agendamento (se permitido).

**Headers:**
```
Authorization: Bearer {token}
```

**Request:**
```json
{
  "motivo": "Imprevisto pessoal"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Agendamento cancelado"
}
```

---

## 📄 Documentos

### GET `/patient/documents`
Listar todos os documentos do paciente.

**Query Params:**
- `tipo` (opcional): `carteirinha`, `comprovante`, `exame`, `prescricao`

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "total": 12,
  "documentos": [
    {
      "id": 789,
      "tipo": "exame",
      "titulo": "Hemograma Completo",
      "descricao": "Resultado de exame laboratorial",
      "data": "2026-07-15T10:00:00-03:00",
      "profissional": "Dr. João Médico",
      "arquivo_url": "https://...pdf",
      "arquivo_tipo": "application/pdf",
      "tamanho_kb": 245
    },
    {
      "id": 788,
      "tipo": "prescricao",
      "titulo": "Prescrição Médica",
      "data": "2026-07-10T15:30:00-03:00",
      "medicamentos": ["Paracetamol 750mg", "Dipirona 1g"],
      "arquivo_url": "https://...pdf"
    }
  ]
}
```

---

### GET `/patient/card`
Obter carteirinha digital do paciente.

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "carteirinha": {
    "codigo": "PAC-2026-123",
    "nome": "João Silva",
    "cpf": "123.456.789-00",
    "plano": "Essencial",
    "numero": "123456789",
    "validade": "31/12/2027",
    "qrcode_data": "unio://patient/123456789",
    "qrcode_image_url": "https://...qrcode.png",
    "barcode": "1234567890123",
    "apple_wallet_url": "https://...pkpass",
    "google_wallet_url": "https://...jwt"
  }
}
```

---

## 💊 Prescrições

### GET `/patient/prescriptions`
Listar prescrições médicas.

**Query Params:**
- `status` (opcional): `ativa`, `concluida`, `todas`

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "total": 3,
  "prescricoes": [
    {
      "id": 999,
      "data": "2026-07-10T15:30:00-03:00",
      "medico": {
        "nome": "Dr. João Médico",
        "crm": "123456",
        "especialidade": "Cardiologia"
      },
      "medicamentos": [
        {
          "nome": "Paracetamol 750mg",
          "posologia": "1 comprimido a cada 6 horas",
          "duracao": "7 dias",
          "observacoes": "Tomar após as refeições"
        }
      ],
      "status": "ativa",
      "arquivo_pdf_url": "https://...pdf"
    }
  ]
}
```

---

## 📊 Códigos de Status HTTP

- `200 OK`: Requisição bem-sucedida
- `201 Created`: Recurso criado com sucesso
- `400 Bad Request`: Dados inválidos
- `401 Unauthorized`: Token ausente ou inválido
- `403 Forbidden`: Sem permissão para acessar
- `404 Not Found`: Recurso não encontrado
- `500 Internal Server Error`: Erro no servidor
- `501 Not Implemented`: Endpoint em desenvolvimento

---

## 🔒 Segurança

1. **HTTPS Obrigatório**: Todas as requisições devem usar HTTPS
2. **Rate Limiting**: Máximo de 60 requisições por minuto
3. **Token Expiration**: Tokens expiram em 24 horas
4. **Refresh Token**: Use `/auth/refresh` para renovar tokens

---

## 📱 SDKs Disponíveis

- **React Native**: `@unio/mobile-sdk-react-native`
- **Flutter**: `unio_mobile_sdk`
- **Swift (iOS)**: `UnioMobileSDK`
- **Kotlin (Android)**: `com.unio.mobile:sdk`

---

## 🆘 Suporte

- **Documentação**: https://docs.unio.com.br/mobile-api
- **Email**: dev@unio.com.br
- **Slack**: #unio-mobile-api

---

**Versão:** 1.0.0  
**Última atualização:** 18/07/2026
