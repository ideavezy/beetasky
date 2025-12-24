# Documents Module - System Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         DOCUMENTS MODULE ARCHITECTURE                     │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                            FRONTEND (React)                               │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                           │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐      │
│  │  Portal Pages    │  │ Public Pages     │  │  Components      │      │
│  ├──────────────────┤  ├──────────────────┤  ├──────────────────┤      │
│  │ • ContractsPage  │  │ • PublicContract │  │ • TiptapEditor   │      │
│  │ • InvoicesPage   │  │ • PublicInvoice  │  │ • MergeField     │      │
│  │                  │  │   (Token Auth)   │  │   Picker         │      │
│  │ [Search/Filter]  │  │                  │  │ • StatusBadges   │      │
│  │ [Create Button]  │  │ [Sign Contract]  │  │ • LineItemForm   │      │
│  │                  │  │ [Pay Invoice]    │  │ • StripePayment  │      │
│  └────────┬─────────┘  └────────┬─────────┘  └────────┬─────────┘      │
│           │                     │                      │                 │
│           └─────────────────────┼──────────────────────┘                 │
│                                 │                                        │
│  ┌──────────────────────────────┴─────────────────────────────────┐    │
│  │               State Management (Zustand)                        │    │
│  ├─────────────────────────────────────────────────────────────────┤    │
│  │  • useContractStore() - CRUD, send, sign                        │    │
│  │  • useInvoiceStore()  - CRUD, line items, payment              │    │
│  └──────────────────────────┬──────────────────────────────────────┘    │
│                             │                                            │
└─────────────────────────────┼────────────────────────────────────────────┘
                              │
                              │ REST API (axios)
                              │
┌─────────────────────────────┼────────────────────────────────────────────┐
│                             ▼                                            │
│                       BACKEND (Laravel)                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                           │
│  ┌──────────────────────────────────────────────────────────────┐       │
│  │                    API CONTROLLERS                            │       │
│  ├──────────────────────────────────────────────────────────────┤       │
│  │ • ContractTemplateController  • InvoiceController            │       │
│  │ • ContractController           • InvoiceTemplateController   │       │
│  │ • PaymentController            • PublicDocumentController    │       │
│  └────────┬─────────────────────────────────────────────────────┘       │
│           │                                                              │
│           ▼                                                              │
│  ┌──────────────────────────────────────────────────────────────┐       │
│  │                    SERVICE CLASSES                            │       │
│  ├──────────────────────────────────────────────────────────────┤       │
│  │ • MergeFieldService      - Extract/replace {{variables}}     │       │
│  │ • DocumentPdfService     - Generate PDFs (dompdf)            │       │
│  │ • StripePaymentService   - Process payments (per-company)    │       │
│  │ • DocumentEmailService   - Send emails via queue             │       │
│  └────────┬─────────────────────────────────────────────────────┘       │
│           │                                                              │
│           ▼                                                              │
│  ┌──────────────────────────────────────────────────────────────┐       │
│  │                    ELOQUENT MODELS                            │       │
│  ├──────────────────────────────────────────────────────────────┤       │
│  │ • ContractTemplate  • Invoice         • Payment              │       │
│  │ • Contract          • InvoiceTemplate • InvoiceEvent         │       │
│  │ • ContractEvent     • InvoiceLineItem                        │       │
│  └────────┬─────────────────────────────────────────────────────┘       │
│           │                                                              │
│           ▼                                                              │
│  ┌──────────────────────────────────────────────────────────────┐       │
│  │                    QUEUE JOBS                                 │       │
│  ├──────────────────────────────────────────────────────────────┤       │
│  │ • SendContractEmail   → Email with public link               │       │
│  │ • SendInvoiceEmail    → Email with payment link              │       │
│  │ • GenerateContractPdf → Background PDF generation            │       │
│  │ • GenerateInvoicePdf  → Background PDF generation            │       │
│  └────────┬─────────────────────────────────────────────────────┘       │
│           │                                                              │
└───────────┼──────────────────────────────────────────────────────────────┘
            │
            ▼
┌───────────────────────────────────────────────────────────────────────────┐
│                         EXTERNAL SERVICES                                 │
├───────────────────────────────────────────────────────────────────────────┤
│                                                                           │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐│
│  │   Supabase   │  │    Stripe    │  │  Bunny CDN   │  │    Email     ││
│  │  (Database)  │  │   Payments   │  │   Storage    │  │   Service    ││
│  ├──────────────┤  ├──────────────┤  ├──────────────┤  ├──────────────┤│
│  │ • PostgreSQL │  │ • PaymentInt │  │ • PDF Files  │  │ • SMTP/SES   ││
│  │ • pgvector   │  │ • Charges    │  │ • Signed     │  │ • Mailtrap   ││
│  │ • Realtime   │  │ • Webhooks   │  │   Documents  │  │ • Templates  ││
│  └──────────────┘  └──────────────┘  └──────────────┘  └──────────────┘│
│                                                                           │
└───────────────────────────────────────────────────────────────────────────┘


════════════════════════════════════════════════════════════════════════════
                             WORKFLOW DIAGRAMS
════════════════════════════════════════════════════════════════════════════

┌───────────────────────────────────────────────────────────────────────────┐
│                        CONTRACT WORKFLOW                                  │
└───────────────────────────────────────────────────────────────────────────┘

  1. CREATE          2. SEND             3. VIEW            4. SIGN
  ┌─────────┐       ┌─────────┐        ┌─────────┐       ┌─────────┐
  │ Staff   │       │ Staff   │        │ Client  │       │ Client  │
  │ Portal  │       │ Portal  │        │ Email   │       │ Browser │
  └────┬────┘       └────┬────┘        └────┬────┘       └────┬────┘
       │                 │                  │                  │
       │ Create Contract │                  │                  │
       ├────────────────►│                  │                  │
       │                 │                  │                  │
       │                 │ Send Email       │                  │
       │                 ├─────────────────►│                  │
       │                 │ (Queue Job)      │                  │
       │                 │                  │                  │
       │                 │            Click Link              │
       │                 │                  ├─────────────────►│
       │                 │                  │                  │
       │                 │                  │  View Contract   │
       │                 │                  │  [Token Auth]    │
       │                 │◄─────────────────┤                  │
       │                 │ (Log 'viewed')   │                  │
       │                 │                  │                  │
       │                 │                  │  ✓ Checkbox      │
       │                 │                  │  Enter Name      │
       │                 │◄─────────────────┼──────────────────┤
       │                 │ (Sign Contract)  │                  │
       │                 │                  │                  │
       │                 │ Generate PDF     │                  │
       │                 │ (Queue Job)      │                  │
       │                 │                  │                  │
       │                 │ Send Confirmation│                  │
       │                 ├─────────────────►│                  │
       │                 │ Email (Both)     │                  │
       │                 │                  │                  │
       ▼                 ▼                  ▼                  ▼
   [Draft]           [Sent]             [Viewed]           [Signed]


┌───────────────────────────────────────────────────────────────────────────┐
│                        INVOICE WORKFLOW                                   │
└───────────────────────────────────────────────────────────────────────────┘

  1. CREATE          2. SEND             3. VIEW            4. PAY
  ┌─────────┐       ┌─────────┐        ┌─────────┐       ┌─────────┐
  │ Staff   │       │ Staff   │        │ Client  │       │ Client  │
  │ Portal  │       │ Portal  │        │ Email   │       │ Browser │
  └────┬────┘       └────┬────┘        └────┬────┘       └────┬────┘
       │                 │                  │                  │
       │ Create Invoice  │                  │                  │
       ├────────────────►│                  │                  │
       │ + Line Items    │                  │                  │
       │                 │                  │                  │
       │                 │ Send Email       │                  │
       │                 ├─────────────────►│                  │
       │                 │ (Queue Job)      │                  │
       │                 │                  │                  │
       │                 │            Click Link              │
       │                 │                  ├─────────────────►│
       │                 │                  │                  │
       │                 │                  │  View Invoice    │
       │                 │                  │  [Token Auth]    │
       │                 │◄─────────────────┤                  │
       │                 │ (Log 'viewed')   │                  │
       │                 │                  │                  │
       │                 │                  │  Click "Pay Now" │
       │                 │◄─────────────────┼──────────────────┤
       │                 │ PaymentIntent    │                  │
       │                 ├──────────────────┼─────────────────►│
       │                 │                  │  (Stripe Form)   │
       │                 │                  │                  │
       │                 │                  │  Enter Card      │
       │                 │◄─────────────────┼──────────────────┤
       │                 │ Process Payment  │                  │
       │                 │                  │                  │
       │      ┌──────────┤                  │                  │
       │      │  Stripe  │                  │                  │
       │      │ Webhook  │                  │                  │
       │      └──────────┤                  │                  │
       │                 │                  │                  │
       │                 │ Generate PDF     │                  │
       │                 │ (Queue Job)      │                  │
       │                 │                  │                  │
       │                 │ Send Receipt     │                  │
       │                 ├─────────────────►│                  │
       │                 │ Email            │                  │
       │                 │                  │                  │
       ▼                 ▼                  ▼                  ▼
   [Draft]           [Sent]             [Viewed]            [Paid]


════════════════════════════════════════════════════════════════════════════
                           DATABASE SCHEMA
════════════════════════════════════════════════════════════════════════════

┌──────────────────────────┐     ┌──────────────────────────┐
│  contract_templates      │     │   invoice_templates      │
├──────────────────────────┤     ├──────────────────────────┤
│ id (uuid)                │     │ id (uuid)                │
│ company_id (uuid) ──────┐│     │ company_id (uuid) ──────┐│
│ name                     ││     │ name                     ││
│ sections (jsonb)         ││     │ layout (jsonb)           ││
│ merge_fields (jsonb)     ││     │ default_terms            ││
│ clickwrap_text           ││     │ default_tax_rate         ││
│ is_active                ││     │ is_default               ││
└─────────┬────────────────┘│     └──────────┬───────────────┘│
          │                 │                │                │
          │                 │                │                │
          │ 1:N             │                │ 1:N            │
          ▼                 │                ▼                │
┌──────────────────────────┐│     ┌──────────────────────────┐│
│      contracts           ││     │       invoices           ││
├──────────────────────────┤│     ├──────────────────────────┤│
│ id (uuid)                ││     │ id (uuid)                ││
│ company_id (uuid) ───────┘│     │ company_id (uuid) ───────┘│
│ template_id (uuid) ───────┘     │ template_id (uuid) ───────┘
│ contact_id (uuid)        │      │ contact_id (uuid)        │
│ project_id (uuid)        │      │ project_id (uuid)        │
│ title                    │      │ invoice_number           │
│ contract_number          │      │ issue_date               │
│ contract_type            │      │ due_date                 │
│ pricing_data (jsonb)     │      │ subtotal (decimal)       │
│ rendered_sections (jsonb)│      │ tax_amount (decimal)     │
│ merge_field_values       │      │ discount_amount          │
│ status (enum)            │      │ total (decimal)          │
│ clickwrap_text           │      │ amount_paid              │
│ client_signed_at         │      │ amount_due               │
│ client_signed_by         │      │ status (enum)            │
│ client_ip_address        │      │ payment_terms            │
│ pdf_path                 │      │ pdf_path                 │
│ token (unique)           │      │ token (unique)           │
│ expires_at               │      │ stripe_payment_intent_id │
└─────────┬────────────────┘      └──────────┬───────────────┘
          │                                  │
          │ 1:N                              │ 1:N
          ▼                                  ▼
┌──────────────────────────┐      ┌──────────────────────────┐
│   contract_events        │      │   invoice_line_items     │
├──────────────────────────┤      ├──────────────────────────┤
│ id (uuid)                │      │ id (uuid)                │
│ contract_id (uuid) ──────┘      │ invoice_id (uuid) ───────┘
│ event_type               │      │ description              │
│ event_data (jsonb)       │      │ quantity (decimal)       │
│ actor_type               │      │ unit_price (decimal)     │
│ actor_id                 │      │ amount (decimal)         │
│ ip_address               │      │ order (int)              │
│ user_agent               │      └──────────────────────────┘
│ created_at               │                │
└──────────────────────────┘                │ N:1
                                            ▼
                                  ┌──────────────────────────┐
                                  │   invoice_events         │
                                  ├──────────────────────────┤
                                  │ id (uuid)                │
                                  │ invoice_id (uuid) ───────┘
                                  │ event_type               │
                                  │ event_data (jsonb)       │
                                  │ actor_type               │
                                  │ actor_id                 │
                                  │ ip_address               │
                                  │ user_agent               │
                                  │ created_at               │
                                  └──────────────────────────┘
                                            │
                                            │ N:1
                                            ▼
                                  ┌──────────────────────────┐
                                  │      payments            │
                                  ├──────────────────────────┤
                                  │ id (uuid)                │
                                  │ company_id (uuid)        │
                                  │ invoice_id (uuid) ───────┘
                                  │ amount (decimal)         │
                                  │ currency                 │
                                  │ payment_method           │
                                  │ status (enum)            │
                                  │ stripe_payment_intent_id │
                                  │ stripe_charge_id         │
                                  │ receipt_url              │
                                  │ processed_at             │
                                  └──────────────────────────┘


════════════════════════════════════════════════════════════════════════════
                         MERGE FIELD SYSTEM
════════════════════════════════════════════════════════════════════════════

Template Content:
  "This agreement is made between {{company.name}} and {{client.full_name}}
   for the project {{project.name}} with a budget of {{project.budget}}."

                      ▼ MergeFieldService.replaceSections()

Rendered Content:
  "This agreement is made between Acme Corp and John Doe
   for the project Website Redesign with a budget of $10,000.00."

Available Merge Fields:
  {{client.first_name}}      →  "John"
  {{client.last_name}}       →  "Doe"
  {{client.full_name}}       →  "John Doe"
  {{client.email}}           →  "john@example.com"
  {{client.phone}}           →  "555-1234"
  {{client.organization}}    →  "Acme Inc"
  {{project.name}}           →  "Website Redesign"
  {{project.description}}    →  "Complete website overhaul"
  {{project.start_date}}     →  "2024-01-15"
  {{project.due_date}}       →  "2024-03-15"
  {{project.budget}}         →  "$10,000.00"
  {{company.name}}           →  "Your Company Name"
  {{today}}                  →  "2024-01-15"
  {{contract.created_date}}  →  "2024-01-15"


════════════════════════════════════════════════════════════════════════════
                         SECURITY & TOKENS
════════════════════════════════════════════════════════════════════════════

Public Access Flow:

  1. Contract/Invoice Created
     ↓
  2. Generate Unique Token (Str::random(64))
     ↓
  3. Store Token in Database
     ↓
  4. Generate Public URL:
     https://app.example.com/public/contracts/{token}
     ↓
  5. Client Accesses URL
     ↓
  6. Middleware Validates Token:
     - Check token exists
     - Check not expired (expires_at)
     - Check status allows access
     ↓
  7. Log Event (viewed) with IP & User Agent
     ↓
  8. Display Document
     ↓
  9. Client Action (Sign/Pay)
     ↓
  10. Update Status & Log Event


════════════════════════════════════════════════════════════════════════════
                         STATUS TRANSITIONS
════════════════════════════════════════════════════════════════════════════

Contract Statuses:
  draft → sent → viewed → signed ✓
    ↓      ↓       ↓
  cancelled      declined
    ↓      ↓       ↓
         expired

Invoice Statuses:
  draft → sent → viewed → partially_paid → paid ✓
    ↓      ↓       ↓           ↓
  cancelled            overdue


════════════════════════════════════════════════════════════════════════════
                         EVENT TRACKING
════════════════════════════════════════════════════════════════════════════

Contract Events:
  • created           - When contract is created
  • sent              - When email is sent to client
  • viewed            - When client opens public link
  • signed            - When client signs contract
  • declined          - When client declines
  • expired           - When contract expires
  • cancelled         - When staff cancels
  • pdf_generated     - When PDF is created
  • send_failed       - When email fails

Invoice Events:
  • created           - When invoice is created
  • sent              - When email is sent to client
  • viewed            - When client opens public link
  • payment_initiated - When payment starts
  • payment_succeeded - When payment completes
  • payment_failed    - When payment fails
  • partial_payment   - When partial payment received
  • paid              - When fully paid
  • overdue           - When due date passes
  • cancelled         - When staff cancels
  • pdf_generated     - When PDF is created
  • send_failed       - When email fails

Each Event Stores:
  • event_type        - Type of event
  • event_data        - JSON payload with details
  • actor_type        - 'user' or 'client'
  • actor_id          - Who performed action
  • ip_address        - Client IP
  • user_agent        - Browser info
  • created_at        - Timestamp


════════════════════════════════════════════════════════════════════════════
```

