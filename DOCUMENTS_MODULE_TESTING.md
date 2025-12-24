# Documents Module - Testing Guide

This guide provides step-by-step testing procedures for the Documents module's Contract and Invoice workflows.

## 📋 Prerequisites

Before testing, ensure:

1. ✅ Database migrations are run
2. ✅ Laravel queue worker is running: `php artisan queue:work`
3. ✅ Frontend dev server is running: `pnpm dev`
4. ✅ Stripe test keys are configured in company settings
5. ✅ Email is configured (Mailtrap or similar for testing)

## 🧪 Test Environment Setup

### 1. Create Test Data

```bash
# Create a test company, user, contact, and project via Tinker
php artisan tinker

# Create test contact
$contact = App\Models\Contact::create([
    'company_id' => 'your-company-id',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john.doe@example.com',
    'phone' => '555-1234',
]);

# Create test project
$project = App\Models\Project::create([
    'company_id' => 'your-company-id',
    'name' => 'Website Redesign',
    'description' => 'Complete website overhaul',
    'status' => 'active',
    'budget' => 10000,
]);
```

### 2. Configure Stripe Test Keys

1. Go to Settings → Integrations
2. Add Stripe test keys:
   - Publishable Key: `pk_test_...`
   - Secret Key: `sk_test_...`
3. Save configuration

---

## 📝 Contract Workflow Testing

### Test 1: Create Contract Template

**Goal**: Verify template creation with sections and merge fields

**Steps**:
1. Navigate to `/documents/contracts/templates`
2. Click "New Template"
3. Fill in:
   - Name: "Standard Service Agreement"
   - Description: "Standard agreement for service projects"
4. Add sections:
   - Heading: "Service Agreement"
   - Paragraph with Tiptap: "This agreement is made between {{company.name}} and {{client.full_name}}"
   - Add formatting: Bold, italic, lists
5. Configure pricing:
   - Type: Fixed Price
   - Default amount: $5000
6. Set clickwrap text: "I agree to the terms outlined in this agreement"
7. Save template

**Expected Result**:
- ✅ Template created successfully
- ✅ Sections saved with proper order
- ✅ Merge fields detected and stored
- ✅ Template appears in templates list

---

### Test 2: Create Contract from Template

**Goal**: Create a new contract and auto-fill merge fields

**Steps**:
1. Navigate to `/documents/contracts`
2. Click "New Contract"
3. Select:
   - Template: "Standard Service Agreement"
   - Client: John Doe
   - Project: Website Redesign
4. Fill additional details:
   - Title: "Website Redesign Agreement"
   - Contract type: Fixed Price
   - Amount: $10,000
5. Review preview (merge fields should be replaced)
6. Save as draft

**Expected Result**:
- ✅ Contract created with status "draft"
- ✅ Merge fields replaced with actual data:
  - `{{company.name}}` → Your Company Name
  - `{{client.full_name}}` → John Doe
  - `{{project.name}}` → Website Redesign
- ✅ Pricing data saved correctly
- ✅ Contract appears in contracts list

**API Call**:
```http
POST /v1/contracts
{
  "template_id": "uuid",
  "contact_id": "uuid",
  "project_id": "uuid",
  "title": "Website Redesign Agreement",
  "contract_type": "fixed_price",
  "pricing_data": {
    "amount": 10000,
    "currency": "USD"
  }
}
```

---

### Test 3: Send Contract to Client

**Goal**: Send contract email and generate public link

**Steps**:
1. Open contract detail page
2. Click "Send Contract"
3. Verify recipient email (pre-filled from contact)
4. Add optional message
5. Click "Send"

**Expected Result**:
- ✅ Contract status changes to "sent"
- ✅ `sent_at` timestamp recorded
- ✅ Token generated for public access
- ✅ Email queued (check queue jobs)
- ✅ Event logged: `sent`
- ✅ Public URL displayed: `https://app.example.com/public/contracts/{token}`

**Queue Job**:
- Check `jobs` table for `SendContractEmail`
- Run queue worker: `php artisan queue:work`
- Verify email sent to Mailtrap/recipient

**Email Contents**:
- Subject: "Contract: Website Redesign Agreement"
- Body: Contains public link
- CTA button: "Review and Sign"

---

### Test 4: Client Views Contract (Public Page)

**Goal**: Track contract view event

**Steps**:
1. Copy public contract URL from email
2. Open in incognito window (simulates client)
3. Contract page loads with:
   - Contract title and number
   - Full rendered content (merge fields replaced)
   - Pricing information
   - Clickwrap checkbox
   - Sign button (disabled until agreed)

**Expected Result**:
- ✅ Contract loads successfully (token auth)
- ✅ Event logged: `viewed` with IP and user agent
- ✅ Contract status updates to "viewed"
- ✅ Timestamp recorded

**API Call** (automatic on page load):
```http
GET /public/contracts/{token}
```

---

### Test 5: Client Signs Contract (Clickwrap)

**Goal**: Verify clickwrap signature process

**Steps**:
1. On public contract page:
2. Read contract content
3. Enter full name: "John Doe"
4. Check agreement checkbox
5. Click "Sign Contract"

**Expected Result**:
- ✅ Contract status changes to "signed"
- ✅ `client_signed_at` timestamp recorded
- ✅ `client_signed_by` set to "John Doe"
- ✅ `client_ip_address` recorded
- ✅ Event logged: `signed`
- ✅ Success message displayed
- ✅ PDF generation job queued
- ✅ Confirmation email sent to both parties

**API Call**:
```http
POST /public/contracts/{token}/sign
{
  "signed_by": "John Doe"
}
```

**Queue Jobs**:
- `GenerateContractPdf` - Creates signed PDF
- `SendContractEmail` - Sends confirmation emails

---

### Test 6: Download Signed PDF

**Goal**: Verify PDF generation and storage

**Steps**:
1. Wait for PDF generation job to complete
2. In portal, open signed contract
3. Click "Download PDF"

**Expected Result**:
- ✅ PDF file exists at `pdf_path`
- ✅ PDF contains:
  - All contract content
  - Signature information
  - Signed date and time
  - Audit trail footer
- ✅ File stored in Bunny CDN (or configured storage)
- ✅ `pdf_generated_at` timestamp recorded

---

### Test 7: Contract Declined

**Goal**: Handle contract decline scenario

**Steps**:
1. Create and send another contract
2. On public page, click "Decline" (if button exists)
3. Or: In portal, change status to "declined"

**Expected Result**:
- ✅ Status changes to "declined"
- ✅ Event logged: `declined`
- ✅ Optional: Reason captured
- ✅ Email notification sent

---

### Test 8: Contract Expiration

**Goal**: Verify automatic expiration

**Steps**:
1. Create contract with `expires_at` in the past
2. Run scheduled command (if implemented): `php artisan schedule:run`
3. Or manually update status via API

**Expected Result**:
- ✅ Status changes to "expired"
- ✅ Event logged: `expired`
- ✅ Cannot be signed after expiration

---

## 💰 Invoice Workflow Testing

### Test 9: Create Invoice Template

**Goal**: Create reusable invoice template

**Steps**:
1. Navigate to `/documents/invoices/templates`
2. Click "New Template"
3. Configure:
   - Name: "Standard Invoice"
   - Layout: Modern
   - Default terms: "Payment due within 30 days"
   - Tax rate: 10%
   - Tax label: "GST"
4. Save template

**Expected Result**:
- ✅ Template created
- ✅ Available for invoice creation
- ✅ Appears in templates list

---

### Test 10: Create Invoice

**Goal**: Create invoice with line items

**Steps**:
1. Navigate to `/documents/invoices`
2. Click "New Invoice"
3. Select:
   - Template: "Standard Invoice"
   - Client: John Doe
   - Project: Website Redesign (optional)
4. Fill details:
   - Invoice number: INV-001 (auto-generated)
   - Issue date: Today
   - Due date: +30 days
5. Add line items:
   - Description: "Website Design", Qty: 1, Unit Price: $5000
   - Description: "Development", Qty: 40, Unit Price: $100
6. Set tax: 10%
7. Add discount: 5% (optional)
8. Save as draft

**Expected Result**:
- ✅ Invoice created with status "draft"
- ✅ Calculations correct:
  - Subtotal: $9,000
  - Discount: -$450
  - Tax: $855
  - Total: $9,405
  - Amount Due: $9,405
- ✅ Line items saved with proper `order`
- ✅ Invoice appears in list

**API Call**:
```http
POST /v1/invoices
{
  "template_id": "uuid",
  "contact_id": "uuid",
  "project_id": "uuid",
  "invoice_number": "INV-001",
  "issue_date": "2024-01-15",
  "due_date": "2024-02-14",
  "line_items": [
    {
      "description": "Website Design",
      "quantity": 1,
      "unit_price": 5000
    },
    {
      "description": "Development",
      "quantity": 40,
      "unit_price": 100
    }
  ],
  "tax_rate": 10,
  "discount_rate": 5
}
```

---

### Test 11: Send Invoice to Client

**Goal**: Send invoice email with payment link

**Steps**:
1. Open invoice detail page
2. Click "Send Invoice"
3. Verify recipient email
4. Click "Send"

**Expected Result**:
- ✅ Invoice status changes to "sent"
- ✅ `sent_at` timestamp recorded
- ✅ Token generated
- ✅ Email queued
- ✅ Event logged: `sent`
- ✅ Public URL displayed

**Queue Job**:
- `SendInvoiceEmail` - Sends email with payment link

**Email Contents**:
- Subject: "Invoice INV-001"
- Body: Invoice summary
- CTA button: "View and Pay"

---

### Test 12: Client Views Invoice

**Goal**: Track invoice view

**Steps**:
1. Open public invoice URL
2. Review invoice details:
   - Line items
   - Subtotal, tax, discount
   - Total and amount due
   - Payment terms

**Expected Result**:
- ✅ Invoice loads (token auth)
- ✅ Event logged: `viewed`
- ✅ Status updates to "viewed"
- ✅ "Pay Now" button visible

---

### Test 13: Client Pays Invoice (Stripe)

**Goal**: Complete payment via Stripe

**Steps**:
1. On public invoice page
2. Click "Pay $9,405.00 Now"
3. Stripe payment form loads
4. Enter test card:
   - Number: `4242 4242 4242 4242`
   - Expiry: Any future date
   - CVC: Any 3 digits
   - ZIP: Any 5 digits
5. Click "Pay"

**Expected Result**:
- ✅ Payment Intent created via Stripe API
- ✅ Payment processes successfully
- ✅ Invoice status changes to "paid"
- ✅ Payment record created:
  - Amount: $9,405
  - Status: "succeeded"
  - Stripe IDs recorded
- ✅ `amount_paid` updated
- ✅ `amount_due` = $0
- ✅ Event logged: `paid`
- ✅ Success page displayed
- ✅ Receipt email sent
- ✅ PDF generated

**API Calls**:
```http
POST /public/invoices/{token}/payment-intent
Response: {
  "client_secret": "pi_xxx_secret_xxx",
  "publishable_key": "pk_test_xxx"
}

POST /v1/payments/webhook
(Stripe webhook after successful payment)
```

**Database Records**:
- `payments` table has new record
- `invoice_events` has "paid" event
- `invoices.status` = "paid"

---

### Test 14: Partial Payment

**Goal**: Handle partial payment scenario

**Steps**:
1. Create invoice for $1000
2. Client pays $500 (simulate via Stripe)

**Expected Result**:
- ✅ Status changes to "partially_paid"
- ✅ `amount_paid` = $500
- ✅ `amount_due` = $500
- ✅ Payment record created
- ✅ Event logged: `partial_payment`
- ✅ Invoice still shows "Pay" button for remaining amount

---

### Test 15: Overdue Invoice

**Goal**: Track overdue invoices

**Steps**:
1. Create invoice with `due_date` in the past
2. Run scheduled check (if implemented)

**Expected Result**:
- ✅ Status changes to "overdue"
- ✅ Event logged: `overdue`
- ✅ Email notification sent (optional)
- ✅ Badge shows "Overdue" in red

---

### Test 16: Invoice from Project (Time Tracking)

**Goal**: Auto-generate invoice from tracked time

**Steps**:
1. Ensure project has time entries
2. Navigate to project detail
3. Click "Create Invoice"
4. Line items auto-populated from time entries

**Expected Result**:
- ✅ Line items created from tasks/time
- ✅ Descriptions match task names
- ✅ Quantities calculated from hours
- ✅ Unit prices from rates

---

## 🔒 Security Testing

### Test 17: Token Expiration

**Goal**: Verify expired tokens are rejected

**Steps**:
1. Create contract with `expires_at` in past
2. Try accessing public URL

**Expected Result**:
- ✅ Error message: "Contract has expired"
- ✅ Cannot sign

---

### Test 18: Invalid Token

**Goal**: Verify invalid tokens are rejected

**Steps**:
1. Try accessing: `/public/contracts/invalid-token-123`

**Expected Result**:
- ✅ 404 or "Contract not found"

---

### Test 19: Company Isolation

**Goal**: Ensure multi-tenancy works

**Steps**:
1. Login as User A (Company A)
2. Create contract
3. Login as User B (Company B)
4. Try accessing Company A's contract

**Expected Result**:
- ✅ Contract not visible in list
- ✅ Direct access returns 403/404
- ✅ Data properly scoped by `company_id`

---

## 📊 Event Tracking Verification

### Test 20: Audit Trail

**Goal**: Verify all events are logged

**Steps**:
1. Perform full workflow (create → send → sign)
2. Check database:
   ```sql
   SELECT * FROM contract_events WHERE contract_id = 'xxx';
   ```

**Expected Events**:
- ✅ `created`
- ✅ `sent` (with email details)
- ✅ `viewed` (with IP, user agent)
- ✅ `signed` (with signature details)
- ✅ `pdf_generated`

**Event Data Includes**:
- Timestamp
- Actor (user/client)
- IP address
- User agent
- Relevant metadata

---

## 🐛 Error Handling Testing

### Test 21: Stripe Errors

**Goal**: Handle payment failures gracefully

**Steps**:
1. Use Stripe test card: `4000 0000 0000 9995` (declined)
2. Attempt payment

**Expected Result**:
- ✅ Error message displayed
- ✅ Payment status: "failed"
- ✅ Event logged: `payment_failed`
- ✅ User can retry

---

### Test 22: Email Failures

**Goal**: Handle email delivery failures

**Steps**:
1. Configure invalid SMTP settings
2. Send contract
3. Check queue failures

**Expected Result**:
- ✅ Job retries 3 times
- ✅ Final failure logged
- ✅ Event: `send_failed`
- ✅ Admin notification (optional)

---

### Test 23: PDF Generation Failures

**Goal**: Handle PDF errors

**Steps**:
1. Create contract with invalid HTML/CSS
2. Trigger PDF generation

**Expected Result**:
- ✅ Job retries
- ✅ Error logged
- ✅ Event: `pdf_generation_failed`
- ✅ User notified

---

## ✅ Testing Checklist

### Contract Workflow
- [ ] Template creation
- [ ] Contract creation from template
- [ ] Merge field replacement
- [ ] Send contract email
- [ ] Public contract view (token auth)
- [ ] Contract signing (clickwrap)
- [ ] PDF generation
- [ ] Contract decline
- [ ] Contract expiration
- [ ] Event tracking

### Invoice Workflow
- [ ] Template creation
- [ ] Invoice creation
- [ ] Line item calculations (subtotal, tax, discount)
- [ ] Send invoice email
- [ ] Public invoice view
- [ ] Payment Intent creation
- [ ] Stripe payment processing
- [ ] Payment success handling
- [ ] Partial payments
- [ ] Overdue detection
- [ ] Receipt generation

### Security
- [ ] Token authentication
- [ ] Token expiration
- [ ] Company data isolation
- [ ] IP tracking
- [ ] Audit trails

### Error Handling
- [ ] Payment failures
- [ ] Email failures
- [ ] PDF generation failures
- [ ] Invalid tokens
- [ ] Network errors

---

## 📝 Test Results Template

```
Test Date: _______________
Tester: _______________
Environment: [ ] Local [ ] Staging [ ] Production

| Test # | Test Name | Status | Notes |
|--------|-----------|--------|-------|
| 1 | Create Template | [ ] Pass [ ] Fail | |
| 2 | Create Contract | [ ] Pass [ ] Fail | |
| 3 | Send Contract | [ ] Pass [ ] Fail | |
| ... | ... | ... | |

Issues Found:
1. 
2. 
3. 

Overall Result: [ ] All tests passed [ ] Some failures [ ] Critical issues
```

---

## 🚀 Automated Testing (Future)

Consider implementing:
- **Feature Tests**: Laravel feature tests for API endpoints
- **Browser Tests**: Laravel Dusk for UI workflows
- **Unit Tests**: Service classes and helpers
- **Integration Tests**: Stripe webhook handling

Example Feature Test:
```php
public function test_contract_can_be_signed()
{
    $contract = Contract::factory()->create(['status' => 'sent']);
    
    $response = $this->post("/public/contracts/{$contract->token}/sign", [
        'signed_by' => 'John Doe',
    ]);
    
    $response->assertStatus(200);
    $this->assertEquals('signed', $contract->fresh()->status);
}
```

---

**Happy Testing! 🎉**

