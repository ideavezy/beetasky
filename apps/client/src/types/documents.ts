export interface ContractTemplate {
  id: string;
  company_id: string;
  name: string;
  description?: string;
  sections: TemplateSection[];
  merge_fields: MergeField[];
  clickwrap_text: string;
  default_contract_type: 'fixed_price' | 'milestone' | 'subscription';
  default_pricing_data: any;
  is_active: boolean;
  created_by?: string;
  deleted_at?: string;
  created_at: string;
  updated_at: string;
}

export interface TemplateSection {
  id: string;
  type: 'heading' | 'paragraph' | 'list' | 'table' | 'signature';
  content: any;
  order: number;
}

export interface MergeField {
  key: string;
  label: string;
  type: 'text' | 'date' | 'currency';
  category: 'client' | 'project' | 'company' | 'system' | 'contract';
}

export interface Contract {
  id: string;
  company_id: string;
  template_id?: string;
  contact_id?: string;
  project_id?: string;
  title: string;
  contract_number?: string;
  contract_type: 'fixed_price' | 'milestone' | 'subscription';
  pricing_data: any;
  rendered_sections: TemplateSection[];
  merge_field_values: Record<string, string>;
  status: 'draft' | 'sent' | 'viewed' | 'signed' | 'declined' | 'expired' | 'cancelled';
  clickwrap_text?: string;
  client_signed_at?: string;
  client_signed_by?: string;
  client_ip_address?: string;
  provider_signed_at?: string;
  provider_signed_by?: string;
  pdf_path?: string;
  pdf_generated_at?: string;
  sent_at?: string;
  sent_by?: string;
  expires_at?: string;
  token?: string;
  notes?: string;
  deleted_at?: string;
  created_at: string;
  updated_at: string;
  template?: ContractTemplate;
  contact?: any;
  project?: any;
}

export interface ContractEvent {
  id: string;
  contract_id: string;
  event_type: string;
  event_data: any;
  actor_type?: string;
  actor_id?: string;
  ip_address?: string;
  user_agent?: string;
  created_at: string;
}

export interface InvoiceTemplate {
  id: string;
  company_id: string;
  name: string;
  description?: string;
  layout: any;
  default_terms: string;
  default_notes?: string;
  default_tax_rate: number;
  default_tax_label: string;
  is_default: boolean;
  is_active: boolean;
  created_by?: string;
  deleted_at?: string;
  created_at: string;
  updated_at: string;
}

export interface Invoice {
  id: string;
  company_id: string;
  template_id?: string;
  contact_id?: string;
  project_id?: string;
  contract_id?: string;
  invoice_number: string;
  title?: string;
  issue_date: string;
  due_date: string;
  status: 'draft' | 'sent' | 'viewed' | 'partially_paid' | 'paid' | 'overdue' | 'cancelled';
  subtotal: number;
  tax_rate: number;
  tax_amount: number;
  discount_rate: number;
  discount_amount: number;
  total: number;
  amount_paid: number;
  amount_due: number;
  currency: string;
  payment_terms?: string;
  notes?: string;
  pdf_path?: string;
  pdf_generated_at?: string;
  sent_at?: string;
  sent_by?: string;
  token?: string;
  stripe_payment_intent_id?: string;
  deleted_at?: string;
  created_at: string;
  updated_at: string;
  template?: InvoiceTemplate;
  contact?: any;
  project?: any;
  contract?: Contract;
  line_items?: InvoiceLineItem[];
}

export interface InvoiceLineItem {
  id: string;
  invoice_id: string;
  description: string;
  quantity: number;
  unit_price: number;
  amount: number;
  task_id?: string;
  order: number;
  created_at: string;
  updated_at: string;
}

export interface Payment {
  id: string;
  company_id: string;
  invoice_id?: string;
  amount: number;
  currency: string;
  payment_method: string;
  status: 'pending' | 'processing' | 'succeeded' | 'failed' | 'refunded';
  stripe_payment_intent_id?: string;
  stripe_charge_id?: string;
  stripe_customer_id?: string;
  receipt_url?: string;
  receipt_number?: string;
  notes?: string;
  processed_at?: string;
  failed_reason?: string;
  refunded_at?: string;
  refund_amount?: number;
  created_at: string;
  updated_at: string;
  invoice?: Invoice;
}

export interface InvoiceEvent {
  id: string;
  invoice_id: string;
  event_type: string;
  event_data: any;
  actor_type?: string;
  actor_id?: string;
  ip_address?: string;
  user_agent?: string;
  created_at: string;
}

