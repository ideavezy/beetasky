import { useState, useEffect } from 'react';
import { Save, Eye, EyeOff, AlertCircle, CheckCircle } from 'lucide-react';
import Layout from '../../components/Layout';
import { api } from '../../lib/api';

export default function DocumentSettingsPage() {
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState(false);
  const [error, setError] = useState('');

  // Stripe Settings
  const [stripePublishableKey, setStripePublishableKey] = useState('');
  const [stripeSecretKey, setStripeSecretKey] = useState('');
  const [stripeWebhookSecret, setStripeWebhookSecret] = useState('');
  const [stripeEnabled, setStripeEnabled] = useState(false);
  const [showSecretKey, setShowSecretKey] = useState(false);
  const [showWebhookSecret, setShowWebhookSecret] = useState(false);

  // Document Settings
  const [contractNumberPrefix, setContractNumberPrefix] = useState('CNT');
  const [invoiceNumberPrefix, setInvoiceNumberPrefix] = useState('INV');
  const [contractAutoExpireDays, setContractAutoExpireDays] = useState(30);
  const [invoiceReminderDays, setInvoiceReminderDays] = useState([7, 3, 1]);

  useEffect(() => {
    loadSettings();
  }, []);

  const loadSettings = async () => {
    setLoading(true);
    setError('');
    try {
      const response = await api.get('/api/v1/company/settings');
      const settings = response.data.data.settings || {};

      // Stripe settings
      if (settings.stripe) {
        setStripePublishableKey(settings.stripe.publishable_key || '');
        setStripeSecretKey(settings.stripe.secret_key || '');
        setStripeWebhookSecret(settings.stripe.webhook_secret || '');
        setStripeEnabled(settings.stripe.enabled || false);
      }

      // Document settings
      if (settings.documents) {
        setContractNumberPrefix(settings.documents.contract_number_prefix || 'CNT');
        setInvoiceNumberPrefix(settings.documents.invoice_number_prefix || 'INV');
        setContractAutoExpireDays(settings.documents.contract_auto_expire_days || 30);
        setInvoiceReminderDays(settings.documents.invoice_auto_reminder_days || [7, 3, 1]);
      }
    } catch (err: any) {
      console.error('Failed to load settings:', err);
      setError('Failed to load settings. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const handleSaveSettings = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setSuccess(false);
    setError('');

    try {
      await api.put('/api/v1/company/settings', {
        settings: {
          stripe: {
            publishable_key: stripePublishableKey,
            secret_key: stripeSecretKey,
            webhook_secret: stripeWebhookSecret,
            enabled: stripeEnabled,
          },
          documents: {
            contract_number_prefix: contractNumberPrefix,
            invoice_number_prefix: invoiceNumberPrefix,
            contract_auto_expire_days: contractAutoExpireDays,
            invoice_auto_reminder_days: invoiceReminderDays,
          },
        },
      });

      setSuccess(true);
      setTimeout(() => setSuccess(false), 3000);
    } catch (err: any) {
      console.error('Failed to save settings:', err);
      setError(err.response?.data?.message || 'Failed to save settings. Please try again.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <Layout>
        <div className="flex items-center justify-center h-64">
          <span className="loading loading-spinner loading-lg text-primary"></span>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
    <div className="p-6">
      <h1 className="text-2xl font-semibold mb-6">Document Settings</h1>

      {success && (
        <div className="alert alert-success mb-6">
          <CheckCircle className="w-5 h-5" />
          <span>Settings saved successfully!</span>
        </div>
      )}

      {error && (
        <div className="alert alert-error mb-6">
          <AlertCircle className="w-5 h-5" />
          <span>{error}</span>
        </div>
      )}

      <form onSubmit={handleSaveSettings}>
        {/* Stripe Integration */}
        <div className="card bg-base-200 shadow-xl p-6 mb-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-semibold text-base-content">Stripe Payment Integration</h2>
            <label className="flex items-center cursor-pointer">
              <input
                type="checkbox"
                className="toggle toggle-primary"
                checked={stripeEnabled}
                onChange={(e) => setStripeEnabled(e.target.checked)}
              />
              <span className="ml-2 text-sm text-base-content">
                {stripeEnabled ? 'Enabled' : 'Disabled'}
              </span>
            </label>
          </div>

          <div className="alert alert-info mb-4">
            <AlertCircle className="w-5 h-5" />
            <div className="text-sm">
              <p className="font-medium">Get your Stripe API keys:</p>
              <a
                href="https://dashboard.stripe.com/apikeys"
                target="_blank"
                rel="noopener noreferrer"
                className="text-primary hover:underline"
              >
                https://dashboard.stripe.com/apikeys
              </a>
            </div>
          </div>

          <div className="space-y-4">
            <div className="form-control">
              <label className="label">
                <span className="label-text">Publishable Key</span>
                <span className="label-text-alt text-base-content/70">Starts with pk_</span>
              </label>
              <input
                type="text"
                placeholder="pk_test_..."
                className="input input-bordered w-full"
                value={stripePublishableKey}
                onChange={(e) => setStripePublishableKey(e.target.value)}
              />
            </div>

            <div className="form-control">
              <label className="label">
                <span className="label-text">Secret Key</span>
                <span className="label-text-alt text-base-content/70">Starts with sk_</span>
              </label>
              <div className="relative">
                <input
                  type={showSecretKey ? 'text' : 'password'}
                  placeholder="sk_test_..."
                  className="input input-bordered w-full pr-12"
                  value={stripeSecretKey}
                  onChange={(e) => setStripeSecretKey(e.target.value)}
                />
                <button
                  type="button"
                  onClick={() => setShowSecretKey(!showSecretKey)}
                  className="btn btn-ghost btn-sm absolute right-2 top-1/2 -translate-y-1/2"
                >
                  {showSecretKey ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            <div className="form-control">
              <label className="label">
                <span className="label-text">Webhook Secret (Optional)</span>
                <span className="label-text-alt text-base-content/70">Starts with whsec_</span>
              </label>
              <div className="relative">
                <input
                  type={showWebhookSecret ? 'text' : 'password'}
                  placeholder="whsec_..."
                  className="input input-bordered w-full pr-12"
                  value={stripeWebhookSecret}
                  onChange={(e) => setStripeWebhookSecret(e.target.value)}
                />
                <button
                  type="button"
                  onClick={() => setShowWebhookSecret(!showWebhookSecret)}
                  className="btn btn-ghost btn-sm absolute right-2 top-1/2 -translate-y-1/2"
                >
                  {showWebhookSecret ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
              <label className="label">
                <span className="label-text-alt text-base-content/70">
                  Configure webhook at: {window.location.origin}/api/stripe/webhook
                </span>
              </label>
            </div>
          </div>
        </div>

        {/* Document Numbering */}
        <div className="card bg-base-200 shadow-xl p-6 mb-6">
          <h2 className="text-xl font-semibold mb-4 text-base-content">Document Numbering</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="form-control">
              <label className="label">
                <span className="label-text">Contract Number Prefix</span>
              </label>
              <input
                type="text"
                placeholder="CNT"
                className="input input-bordered w-full"
                value={contractNumberPrefix}
                onChange={(e) => setContractNumberPrefix(e.target.value.toUpperCase())}
                maxLength={10}
              />
              <label className="label">
                <span className="label-text-alt text-base-content/70">
                  Example: {contractNumberPrefix}-2024-0001
                </span>
              </label>
            </div>

            <div className="form-control">
              <label className="label">
                <span className="label-text">Invoice Number Prefix</span>
              </label>
              <input
                type="text"
                placeholder="INV"
                className="input input-bordered w-full"
                value={invoiceNumberPrefix}
                onChange={(e) => setInvoiceNumberPrefix(e.target.value.toUpperCase())}
                maxLength={10}
              />
              <label className="label">
                <span className="label-text-alt text-base-content/70">
                  Example: {invoiceNumberPrefix}-2024-0001
                </span>
              </label>
            </div>
          </div>
        </div>

        {/* Automation Settings */}
        <div className="card bg-base-200 shadow-xl p-6 mb-6">
          <h2 className="text-xl font-semibold mb-4 text-base-content">Automation Settings</h2>
          <div className="space-y-4">
            <div className="form-control">
              <label className="label">
                <span className="label-text">Contract Auto-Expire (Days)</span>
              </label>
              <input
                type="number"
                min="1"
                max="365"
                className="input input-bordered w-full md:w-64"
                value={contractAutoExpireDays}
                onChange={(e) => setContractAutoExpireDays(parseInt(e.target.value) || 30)}
              />
              <label className="label">
                <span className="label-text-alt text-base-content/70">
                  Contracts will expire after this many days if not signed
                </span>
              </label>
            </div>

            <div className="form-control">
              <label className="label">
                <span className="label-text">Invoice Payment Reminders (Days Before Due)</span>
              </label>
              <div className="flex items-center gap-2 flex-wrap">
                {invoiceReminderDays.map((day, index) => (
                  <div key={index} className="flex items-center gap-2">
                    <input
                      type="number"
                      min="0"
                      max="90"
                      className="input input-bordered w-20"
                      value={day}
                      onChange={(e) => {
                        const newDays = [...invoiceReminderDays];
                        newDays[index] = parseInt(e.target.value) || 0;
                        setInvoiceReminderDays(newDays);
                      }}
                    />
                    {index < invoiceReminderDays.length - 1 && <span className="text-base-content/70">,</span>}
                  </div>
                ))}
              </div>
              <label className="label">
                <span className="label-text-alt text-base-content/70">
                  Reminder emails will be sent these many days before the due date
                </span>
              </label>
            </div>
          </div>
        </div>

        {/* Save Button */}
        <div className="flex justify-end">
          <button type="submit" className="btn btn-primary" disabled={saving}>
            {saving ? (
              <>
                <span className="loading loading-spinner loading-sm"></span>
                Saving...
              </>
            ) : (
              <>
                <Save className="w-4 h-4" />
                Save Settings
              </>
            )}
          </button>
        </div>
      </form>
    </div>
    </Layout>
  );
}
