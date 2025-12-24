import { create } from 'zustand';
import type { Contract, ContractTemplate, ContractEvent } from '../types/documents';
import { api } from '../lib/api';

interface ContractState {
  contracts: Contract[];
  templates: ContractTemplate[];
  selectedContract: Contract | null;
  selectedTemplate: ContractTemplate | null;
  contractEvents: ContractEvent[];
  isLoading: boolean;
  error: string | null;

  // Contract Actions
  fetchContracts: () => Promise<void>;
  fetchContractById: (id: string) => Promise<Contract>;
  createContract: (data: Partial<Contract>) => Promise<Contract>;
  updateContract: (id: string, data: Partial<Contract>) => Promise<Contract>;
  deleteContract: (id: string) => Promise<void>;
  sendContract: (id: string) => Promise<void>;
  generatePdf: (id: string) => Promise<void>;
  fetchContractEvents: (id: string) => Promise<ContractEvent[]>;
  setSelectedContract: (contract: Contract | null) => void;

  // Template Actions
  fetchTemplates: () => Promise<void>;
  fetchTemplateById: (id: string) => Promise<ContractTemplate>;
  createTemplate: (data: Partial<ContractTemplate>) => Promise<ContractTemplate>;
  updateTemplate: (id: string, data: Partial<ContractTemplate>) => Promise<ContractTemplate>;
  deleteTemplate: (id: string) => Promise<void>;
  duplicateTemplate: (id: string) => Promise<ContractTemplate>;
  setSelectedTemplate: (template: ContractTemplate | null) => void;
  generateContractWithAI: (prompt: string, options?: { contract_type?: string; client_name?: string; project_name?: string }) => Promise<{ sections: any[]; clickwrap_text: string }>;
  generateContractSectionWithAI: (
    prompt: string,
    sectionType: 'heading' | 'paragraph',
    templateContext?: {
      template_name?: string;
      contract_type?: string;
      sections?: Array<{ type: string; text?: string; order?: number }>;
    }
  ) => Promise<{ type: 'heading' | 'paragraph'; content: any }>;
  
  // AI Assistant Integration
  pendingAISections: { sections: any[]; clickwrap_text: string } | null;
  setPendingAISections: (data: { sections: any[]; clickwrap_text: string } | null) => void;
  generateContractFromChat: (prompt: string, contractType?: string) => Promise<void>;

  // Public Actions (no auth required)
  signContract: (token: string, signedBy: string) => Promise<void>;
  getPublicContract: (token: string) => Promise<Contract>;
}

export const useContractStore = create<ContractState>((set) => ({
  contracts: [],
  templates: [],
  selectedContract: null,
  selectedTemplate: null,
  contractEvents: [],
  isLoading: false,
  error: null,
  pendingAISections: null,

  // Contract Actions
  fetchContracts: async () => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.get('/api/v1/contracts');
      set({ contracts: response.data.data || [], isLoading: false });
    } catch (error: any) {
      console.error('Failed to fetch contracts:', error);
      set({ error: error.message, isLoading: false, contracts: [] });
    }
  },

  fetchContractById: async (id: string) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.get(`/api/v1/contracts/${id}`);
      const contract = response.data.data;
      set({ selectedContract: contract, isLoading: false });
      return contract;
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  createContract: async (data: Partial<Contract>) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.post('/api/v1/contracts', data);
      const contract = response.data.data;
      set((state) => ({
        contracts: [...state.contracts, contract],
        isLoading: false,
      }));
      return contract;
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  updateContract: async (id: string, data: Partial<Contract>) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.put(`/api/v1/contracts/${id}`, data);
      const contract = response.data.data;
      set((state) => ({
        contracts: state.contracts.map((c) => (c.id === id ? contract : c)),
        selectedContract: state.selectedContract?.id === id ? contract : state.selectedContract,
        isLoading: false,
      }));
      return contract;
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  deleteContract: async (id: string) => {
    set({ isLoading: true, error: null });
    try {
      await api.delete(`/api/v1/contracts/${id}`);
      set((state) => ({
        contracts: state.contracts.filter((c) => c.id !== id),
        isLoading: false,
      }));
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  sendContract: async (id: string) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.post(`/api/v1/contracts/${id}/send`);
      const contract = response.data.data;
      set((state) => ({
        contracts: state.contracts.map((c) => (c.id === id ? contract : c)),
        selectedContract: state.selectedContract?.id === id ? contract : state.selectedContract,
        isLoading: false,
      }));
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  generatePdf: async (id: string) => {
    set({ isLoading: true, error: null });
    try {
      await api.post(`/api/v1/contracts/${id}/generate-pdf`);
      set({ isLoading: false });
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  fetchContractEvents: async (id: string) => {
    try {
      const response = await api.get(`/api/v1/contracts/${id}/events`);
      const events = response.data.data || [];
      set({ contractEvents: events });
      return events;
    } catch (error: any) {
      console.error('Failed to fetch contract events:', error);
      return [];
    }
  },

  setSelectedContract: (contract: Contract | null) => {
    set({ selectedContract: contract });
  },

  // Template Actions
  fetchTemplates: async () => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.get('/api/v1/contract-templates');
      set({ templates: response.data.data || [], isLoading: false });
    } catch (error: any) {
      set({ error: error.message, isLoading: false, templates: [] });
    }
  },

  fetchTemplateById: async (id: string) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.get(`/api/v1/contract-templates/${id}`);
      const template = response.data.data;
      set({ selectedTemplate: template, isLoading: false });
      return template;
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  createTemplate: async (data: Partial<ContractTemplate>) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.post('/api/v1/contract-templates', data);
      const template = response.data.data;
      set((state) => ({
        templates: [...state.templates, template],
        isLoading: false,
      }));
      return template;
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  updateTemplate: async (id: string, data: Partial<ContractTemplate>) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.put(`/api/v1/contract-templates/${id}`, data);
      const template = response.data.data;
      set((state) => ({
        templates: state.templates.map((t) => (t.id === id ? template : t)),
        selectedTemplate: state.selectedTemplate?.id === id ? template : state.selectedTemplate,
        isLoading: false,
      }));
      return template;
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  deleteTemplate: async (id: string) => {
    set({ isLoading: true, error: null });
    try {
      await api.delete(`/api/v1/contract-templates/${id}`);
      set((state) => ({
        templates: state.templates.filter((t) => t.id !== id),
        isLoading: false,
      }));
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  duplicateTemplate: async (id: string) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.post(`/api/v1/contract-templates/${id}/duplicate`);
      const template = response.data.data;
      set((state) => ({
        templates: [...state.templates, template],
        isLoading: false,
      }));
      return template;
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  generateContractWithAI: async (prompt: string, options = {}) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.post('/api/v1/contract-templates/generate-ai', {
        prompt,
        ...options,
      });
      set({ isLoading: false });
      return response.data.data;
    } catch (error: any) {
      const message =
        error?.response?.data?.error ||
        error?.response?.data?.message ||
        error?.message ||
        'Failed to generate contract with AI';
      set({ error: message, isLoading: false });
      throw new Error(message);
    }
  },

  generateContractSectionWithAI: async (prompt, sectionType, templateContext) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.post('/api/v1/contract-templates/generate-section-ai', {
        prompt,
        section_type: sectionType,
        template_context: templateContext || {},
      });
      set({ isLoading: false });
      return response.data.data;
    } catch (error: any) {
      const message =
        error?.response?.data?.error ||
        error?.response?.data?.message ||
        error?.message ||
        'Failed to generate section with AI';
      set({ error: message, isLoading: false });
      throw new Error(message);
    }
  },

  setPendingAISections: (data) => {
    set({ pendingAISections: data });
  },

  generateContractFromChat: async (prompt: string, contractType = 'fixed_price') => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.post('/api/v1/contract-templates/generate-ai', {
        prompt,
        contract_type: contractType,
      });
      // Store in pending state for the template builder to pick up
      set({ 
        pendingAISections: response.data.data,
        isLoading: false 
      });
    } catch (error: any) {
      const message =
        error?.response?.data?.error ||
        error?.response?.data?.message ||
        error?.message ||
        'Failed to generate contract with AI';
      set({ error: message, isLoading: false });
      throw new Error(message);
    }
  },

  setSelectedTemplate: (template: ContractTemplate | null) => {
    set({ selectedTemplate: template });
  },

  // Public Actions
  signContract: async (token: string, signedBy: string) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.post(`/api/public/contracts/${token}/sign`, {
        signed_by: signedBy,
      });
      set({ selectedContract: response.data.data, isLoading: false });
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },

  getPublicContract: async (token: string) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.get(`/api/public/contracts/${token}`);
      const contract = response.data.data;
      set({ selectedContract: contract, isLoading: false });
      return contract;
    } catch (error: any) {
      set({ error: error.message, isLoading: false });
      throw error;
    }
  },
}));
