# Documents Module - Frontend Components

This directory contains all frontend components, pages, and state management for the Documents module.

## 📁 Structure

```
src/
├── components/documents/          # Reusable UI components
│   ├── TiptapEditor.tsx           # Rich text editor (Bold, Italic, Lists, etc.)
│   ├── MergeFieldPicker.tsx       # Dropdown for selecting merge fields
│   ├── ContractStatusBadge.tsx    # Status badge for contracts
│   ├── InvoiceStatusBadge.tsx     # Status badge for invoices
│   ├── InvoiceLineItemForm.tsx    # Table for managing invoice line items
│   └── StripePaymentForm.tsx      # Embedded Stripe payment form
│
├── pages/documents/               # Internal portal pages
│   ├── ContractsPage.tsx          # List all contracts with filters
│   └── InvoicesPage.tsx           # List all invoices with filters
│
├── pages/public/                  # Public-facing pages (client-side)
│   ├── PublicContractPage.tsx     # Contract signing page (token auth)
│   └── PublicInvoicePage.tsx      # Invoice payment page (token auth)
│
├── stores/                        # Zustand state management
│   ├── contracts.ts               # Contract CRUD & actions
│   └── invoices.ts                # Invoice CRUD & actions
│
└── types/
    └── documents.ts               # TypeScript interfaces
```

## 🎨 Components

### TiptapEditor
Rich text editor with formatting toolbar.

**Props:**
- `content: string` - Initial HTML content
- `onChange: (html: string) => void` - Callback when content changes
- `placeholder?: string` - Placeholder text
- `editable?: boolean` - Enable/disable editing
- `onInsertMergeField?: () => void` - Callback for merge field button

**Features:**
- Bold, Italic, Underline, Strikethrough
- Bullet lists & Numbered lists
- Text alignment (left, center, right, justify)
- Insert merge field button

**Usage:**
```tsx
<TiptapEditor
  content={section.content}
  onChange={(html) => updateSection(html)}
  placeholder="Start typing..."
  onInsertMergeField={() => setShowPicker(true)}
/>
```

### MergeFieldPicker
Dropdown menu for selecting merge fields.

**Props:**
- `onSelect: (field: MergeField) => void` - Callback when field is selected

**Features:**
- Searchable field list
- Grouped by category (Client, Project, Company, System, Contract)
- Shows merge field key and label

**Available Fields:**
- **Client**: first_name, last_name, full_name, email, phone, organization
- **Project**: name, description, start_date, due_date, budget
- **Company**: name
- **System**: today
- **Contract**: created_date

**Usage:**
```tsx
<MergeFieldPicker
  onSelect={(field) => {
    // Insert {{field.key}} into editor
    editor.insertContent('{{' + field.key + '}}');
  }}
/>
```

### InvoiceLineItemForm
Table for managing invoice line items with auto-calculation.

**Props:**
- `lineItems: Partial<InvoiceLineItem>[]` - Array of line items
- `onChange: (items: Partial<InvoiceLineItem>[]) => void` - Callback when items change
- `currency?: string` - Currency code (default: USD)

**Features:**
- Add/remove line items
- Auto-calculate amount from quantity × unit_price
- Shows subtotal
- Responsive table design

**Usage:**
```tsx
<InvoiceLineItemForm
  lineItems={invoice.line_items}
  onChange={(items) => setLineItems(items)}
  currency="USD"
/>
```

### StripePaymentForm
Embedded Stripe Elements payment form.

**Props:**
- `clientSecret: string` - Stripe PaymentIntent client secret
- `publishableKey: string` - Stripe publishable key
- `amount: number` - Amount to display
- `currency: string` - Currency code
- `onSuccess: () => void` - Callback when payment succeeds
- `onError: (error: string) => void` - Callback when payment fails

**Features:**
- Dark theme Stripe Elements
- Embedded payment form (no redirect)
- Loading states
- Security notice

**Usage:**
```tsx
<StripePaymentForm
  clientSecret={paymentIntent.client_secret}
  publishableKey={company.stripe_publishable_key}
  amount={invoice.amount_due}
  currency="USD"
  onSuccess={() => navigate('/success')}
  onError={(err) => alert(err)}
/>
```

## 🗃️ State Management

### Contracts Store (`useContractStore`)

**State:**
- `contracts: Contract[]` - All contracts
- `templates: ContractTemplate[]` - All templates
- `selectedContract: Contract | null` - Currently selected contract
- `isLoading: boolean` - Loading state
- `error: string | null` - Error message

**Actions:**
- `fetchContracts()` - Fetch all contracts
- `fetchTemplates()` - Fetch all templates
- `fetchContract(id)` - Fetch single contract
- `createContract(data)` - Create new contract
- `updateContract(id, data)` - Update contract
- `deleteContract(id)` - Delete contract
- `sendContract(id)` - Send contract to client
- `signContract(token, signedBy)` - Sign contract (public)

**Usage:**
```tsx
const { contracts, fetchContracts, createContract } = useContractStore();

useEffect(() => {
  fetchContracts();
}, []);

const handleCreate = async () => {
  const contract = await createContract({
    title: 'Service Agreement',
    contact_id: clientId,
    project_id: projectId,
  });
};
```

### Invoices Store (`useInvoiceStore`)

**State:**
- `invoices: Invoice[]` - All invoices
- `templates: InvoiceTemplate[]` - All templates
- `selectedInvoice: Invoice | null` - Currently selected invoice
- `isLoading: boolean` - Loading state
- `error: string | null` - Error message

**Actions:**
- `fetchInvoices()` - Fetch all invoices
- `fetchTemplates()` - Fetch all templates
- `fetchInvoice(id)` - Fetch single invoice
- `createInvoice(data)` - Create new invoice
- `updateInvoice(id, data)` - Update invoice
- `deleteInvoice(id)` - Delete invoice
- `addLineItem(invoiceId, item)` - Add line item
- `updateLineItem(invoiceId, itemId, data)` - Update line item
- `deleteLineItem(invoiceId, itemId)` - Delete line item
- `sendInvoice(id)` - Send invoice to client
- `createPaymentIntent(token)` - Create Stripe PaymentIntent (public)

**Usage:**
```tsx
const { invoices, fetchInvoices, createInvoice } = useInvoiceStore();

useEffect(() => {
  fetchInvoices();
}, []);

const handleCreate = async () => {
  const invoice = await createInvoice({
    invoice_number: 'INV-001',
    contact_id: clientId,
    issue_date: new Date().toISOString(),
    due_date: addDays(new Date(), 30).toISOString(),
  });
};
```

## 📄 Pages

### ContractsPage
List view of all contracts with search and filters.

**Features:**
- Search by title or contract number
- Filter by status
- Status badges
- Link to detail pages
- Create new contract button

**Route:** `/documents/contracts`

### InvoicesPage
List view of all invoices with search, filters, and stats.

**Features:**
- Total outstanding amount
- Total paid amount
- Search by invoice number or title
- Filter by status
- Responsive table
- Status badges

**Route:** `/documents/invoices`

### PublicContractPage
Client-facing contract signing page.

**Features:**
- Token-based authentication
- View contract content
- Clickwrap agreement checkbox
- Digital signature (name + checkbox)
- Success confirmation
- Print/download option

**Route:** `/public/contracts/:token`

### PublicInvoicePage
Client-facing invoice payment page.

**Features:**
- Token-based authentication
- View invoice details
- Line items breakdown
- Tax and discount calculation
- Embedded Stripe payment
- Success confirmation
- Print receipt option

**Route:** `/public/invoices/:token`

## 🔗 API Integration

All API calls use the `apiClient` from `lib/api.ts`.

**Endpoints:**
```
GET    /v1/contracts
POST   /v1/contracts
GET    /v1/contracts/:id
PUT    /v1/contracts/:id
DELETE /v1/contracts/:id
POST   /v1/contracts/:id/send
POST   /public/contracts/:token/sign

GET    /v1/invoices
POST   /v1/invoices
GET    /v1/invoices/:id
PUT    /v1/invoices/:id
DELETE /v1/invoices/:id
POST   /v1/invoices/:id/send
POST   /public/invoices/:token/payment-intent
```

## 🎨 Design System

All components follow the BeetaSky design system:

- **Theme**: Dark mode by default
- **Colors**: DaisyUI semantic classes
- **Primary**: Golden/amber for CTAs
- **Icons**: Heroicons (outline) only
- **Font**: Poppins (already loaded)
- **Spacing**: Generous padding (min 24px in cards)
- **Components**: DaisyUI buttons, inputs, badges, cards

## 🚀 Getting Started

1. **Install dependencies** (already done):
   ```bash
   pnpm install
   ```

2. **Import components**:
   ```tsx
   import { TiptapEditor } from '@/components/documents/TiptapEditor';
   import { useContractStore } from '@/stores/contracts';
   ```

3. **Add routes** to your router:
   ```tsx
   <Route path="/documents/contracts" element={<ContractsPage />} />
   <Route path="/documents/invoices" element={<InvoicesPage />} />
   <Route path="/public/contracts/:token" element={<PublicContractPage />} />
   <Route path="/public/invoices/:token" element={<PublicInvoicePage />} />
   ```

4. **Configure API client** to point to your Laravel backend

## 📝 Next Steps

- [ ] Add routing to App.tsx
- [ ] Create contract/invoice detail pages
- [ ] Build template builder with drag-and-drop
- [ ] Add contract creation wizard
- [ ] Add invoice creation form
- [ ] Implement settings page for Stripe keys
- [ ] Add event timeline components
- [ ] Test with live API

---

**Note**: Backend API endpoints must be implemented for full functionality.

