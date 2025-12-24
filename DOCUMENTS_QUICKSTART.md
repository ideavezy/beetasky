# Documents Module - Quick Start Guide

## 🚀 Get Up and Running in 5 Minutes

### Step 1: Install Dependencies ✅

```bash
# Frontend dependencies (already installed)
cd apps/client
pnpm install

# Backend dependencies (already installed)
cd ../../backend
composer install
```

### Step 2: Run Database Migrations

```bash
cd backend
php artisan migrate
```

### Step 3: Start Queue Worker

```bash
# Terminal 1: Queue worker for background jobs
php artisan queue:work
```

### Step 4: Start Development Servers

```bash
# Terminal 2: Laravel backend
cd backend
php artisan serve

# Terminal 3: React frontend
cd apps/client
pnpm dev
```

### Step 5: Add Routes to Your App

Edit `apps/client/src/App.tsx` and add these routes:

```tsx
import {
  ContractsPage,
  InvoicesPage,
} from './pages/documents';
import {
  PublicContractPage,
  PublicInvoicePage,
} from './pages/public';

// In your router:
<Route path="/documents/contracts" element={<ContractsPage />} />
<Route path="/documents/invoices" element={<InvoicesPage />} />
<Route path="/public/contracts/:token" element={<PublicContractPage />} />
<Route path="/public/invoices/:token" element={<PublicInvoicePage />} />
```

### Step 6: Configure API Client

Ensure your API client points to Laravel backend:

```typescript
// apps/client/src/lib/api.ts
const API_BASE_URL = process.env.VITE_API_URL || 'http://localhost:8000/api';
```

### Step 7: Add Navigation Links

Add links to your main navigation:

```tsx
<Link to="/documents/contracts">Contracts</Link>
<Link to="/documents/invoices">Invoices</Link>
```

---

## 🎯 Try It Out

### Create Your First Contract

1. Navigate to `/documents/contracts`
2. Click "New Contract"
3. Fill in the details
4. Save and send!

### Create Your First Invoice

1. Navigate to `/documents/invoices`
2. Click "New Invoice"
3. Add line items
4. Save and send!

---

## 📝 Quick Reference

### Import Components

```tsx
// All components
import {
  TiptapEditor,
  MergeFieldPicker,
  ContractStatusBadge,
  InvoiceStatusBadge,
  InvoiceLineItemForm,
  StripePaymentForm,
} from '@/components/documents';

// Stores
import { useContractStore } from '@/stores/contracts';
import { useInvoiceStore } from '@/stores/invoices';
```

### Use Zustand Stores

```tsx
// Contracts
const { contracts, fetchContracts, createContract, sendContract } = useContractStore();

// Invoices
const { invoices, fetchInvoices, createInvoice, sendInvoice } = useInvoiceStore();
```

---

## 🔧 Configuration

### Stripe (Required for Payments)

1. Go to Settings → Integrations
2. Add your Stripe keys:
   - **Test Mode**: `pk_test_...` and `sk_test_...`
   - **Live Mode**: `pk_live_...` and `sk_live_...`

### Email (Required for Sending)

Configure in `backend/.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
```

### Storage (Required for PDFs)

Configure Bunny CDN or use local storage:

```env
FILESYSTEM_DISK=bunny
BUNNY_STORAGE_ZONE=your-zone
BUNNY_ACCESS_KEY=your-key
```

---

## 📚 Documentation

- **Implementation Guide**: `DOCUMENTS_MODULE_IMPLEMENTATION.md`
- **Testing Guide**: `DOCUMENTS_MODULE_TESTING.md`
- **Frontend Reference**: `apps/client/DOCUMENTS_FRONTEND_README.md`
- **Architecture Diagram**: `DOCUMENTS_ARCHITECTURE_DIAGRAM.md`
- **Completion Summary**: `DOCUMENTS_FRONTEND_COMPLETE.md`

---

## 🆘 Need Help?

### Common Issues

**Q: Queue jobs not running?**  
A: Make sure `php artisan queue:work` is running in a terminal.

**Q: PDFs not generating?**  
A: Check that dompdf is installed: `composer show dompdf/dompdf`

**Q: Stripe payment not working?**  
A: Verify Stripe keys are configured in company settings.

**Q: Components not found?**  
A: Check import paths and ensure files are in correct directories.

### Debug Mode

Enable verbose logging:

```env
# backend/.env
LOG_LEVEL=debug
```

Check logs:

```bash
tail -f backend/storage/logs/laravel.log
```

---

## ✅ Checklist

Before going live:

- [ ] Database migrations run
- [ ] Queue worker running
- [ ] Stripe keys configured (live mode)
- [ ] Email configured (production SMTP)
- [ ] Storage configured (Bunny CDN)
- [ ] Routes added to frontend
- [ ] Navigation links added
- [ ] Test contract workflow
- [ ] Test invoice workflow
- [ ] Test payment processing
- [ ] Review security settings
- [ ] SSL certificate installed
- [ ] CORS configured for production

---

## 🎉 You're Ready!

The Documents module is now ready to use. Create contracts, send invoices, and start getting paid!

For detailed workflows and testing procedures, see:
- `DOCUMENTS_MODULE_TESTING.md` - Complete testing guide
- `DOCUMENTS_FRONTEND_COMPLETE.md` - Full implementation details

---

**Built with ❤️ for BeetaSky CRM**

