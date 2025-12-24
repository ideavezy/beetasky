import { create } from 'zustand';
import type { Invoice, InvoiceTemplate, InvoiceLineItem } from '../types/documents';
import { api } from '../lib/api';

interface InvoiceState {
  invoices: Invoice[];
  templates: InvoiceTemplate[];
  selectedInvoice: Invoice | null;
  isLoading: boolean;
  error: string | null;

  // Actions
  fetchInvoices: () => Promise<void>;
  fetchTemplates: () => Promise<void>;
  fetchInvoice: (id: string) => Promise<void>;
  fetchInvoiceById: (id: string) => Promise<any>;
  createInvoice: (data: Partial<Invoice>) => Promise<Invoice>;
  updateInvoice: (id: string, data: Partial<Invoice>) => Promise<Invoice>;
  deleteInvoice: (id: string) => Promise<void>;
  addLineItem: (invoiceId: string, item: Partial<InvoiceLineItem>) => Promise<void>;
  updateLineItem: (invoiceId: string, itemId: string, data: Partial<InvoiceLineItem>) => Promise<void>;
  deleteLineItem: (invoiceId: string, itemId: string) => Promise<void>;
  sendInvoice: (id: string) => Promise<void>;
  createPaymentIntent: (token: string) => Promise<{ client_secret: string; publishable_key: string }>;
  setSelectedInvoice: (invoice: Invoice | null) => void;
}

export const useInvoiceStore = create<InvoiceState>((set, get) => ({
  invoices: [],
  templates: [],
  selectedInvoice: null,
  isLoading: false,
  error: null,

  fetchInvoices: async () => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.get('/api/v1/invoices');
      set({ invoices: response.data.data || [], isLoading: false });
    } catch (error: any) {
      console.error('Failed to fetch invoices:', error);
      set({ error: error.message, isLoading: false, invoices: [] });
    }
  },

  fetchTemplates: async () => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.get('/api/v1/invoice-templates');
      set({ templates: response.data.data, isLoading: false });
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
    }
  },

  fetchInvoice: async (id: string) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.get(`/api/v1/invoices/${id}`);
      set({ selectedInvoice: response.data.data, isLoading: false });
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
    }
  },

  fetchInvoiceById: async (id: string) => {
    const response = await api.get(`/api/v1/invoices/${id}`);
    return response.data.data;
  },

  createInvoice: async (data: Partial<Invoice>) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.post('/api/v1/invoices', data);
      const invoice = response.data.data;
      set((state) => ({
        invoices: [...state.invoices, invoice],
        isLoading: false,
      }));
      return invoice;
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  updateInvoice: async (id: string, data: Partial<Invoice>) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.put(`/api/v1/invoices/${id}`, data);
      const invoice = response.data.data;
      set((state) => ({
        invoices: state.invoices.map((i) => (i.id === id ? invoice : i)),
        selectedInvoice: state.selectedInvoice?.id === id ? invoice : state.selectedInvoice,
        isLoading: false,
      }));
      return invoice;
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  deleteInvoice: async (id: string) => {
    set({ isLoading: true, error: null });
    try {
      await api.delete(`/api/v1/invoices/${id}`);
      set((state) => ({
        invoices: state.invoices.filter((i) => i.id !== id),
        isLoading: false,
      }));
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  addLineItem: async (invoiceId: string, item: Partial<InvoiceLineItem>) => {
    set({ isLoading: true, error: null });
    try {
      await api.post(`/api/v1/invoices/${invoiceId}/line-items`, item);
      await get().fetchInvoice(invoiceId);
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  updateLineItem: async (invoiceId: string, itemId: string, data: Partial<InvoiceLineItem>) => {
    set({ isLoading: true, error: null });
    try {
      await api.put(`/api/v1/invoices/${invoiceId}/line-items/${itemId}`, data);
      await get().fetchInvoice(invoiceId);
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  deleteLineItem: async (invoiceId: string, itemId: string) => {
    set({ isLoading: true, error: null });
    try {
      await api.delete(`/api/v1/invoices/${invoiceId}/line-items/${itemId}`);
      await get().fetchInvoice(invoiceId);
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  sendInvoice: async (id: string) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.post(`/api/v1/invoices/${id}/send`);
      const invoice = response.data.data;
      set((state) => ({
        invoices: state.invoices.map((i) => (i.id === id ? invoice : i)),
        selectedInvoice: state.selectedInvoice?.id === id ? invoice : state.selectedInvoice,
        isLoading: false,
      }));
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  createPaymentIntent: async (token: string) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.post(`/api/public/invoices/${token}/payment-intent`);
      set({ isLoading: false });
      return response.data.data;
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  setSelectedInvoice: (invoice: Invoice | null) => {
    set({ selectedInvoice: invoice });
  },
}));


