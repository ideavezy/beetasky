import { PlusIcon, TrashIcon } from '@heroicons/react/24/outline';
import type { InvoiceLineItem } from '../../types/documents';

interface InvoiceLineItemFormProps {
  lineItems: Partial<InvoiceLineItem>[];
  onChange: (items: Partial<InvoiceLineItem>[]) => void;
  currency?: string;
}

export function InvoiceLineItemForm({
  lineItems,
  onChange,
  currency = 'USD',
}: InvoiceLineItemFormProps) {
  const addLineItem = () => {
    onChange([
      ...lineItems,
      {
        description: '',
        quantity: 1,
        unit_price: 0,
        amount: 0,
        order: lineItems.length,
      },
    ]);
  };

  const removeLineItem = (index: number) => {
    onChange(lineItems.filter((_, i) => i !== index));
  };

  const updateLineItem = (index: number, field: keyof InvoiceLineItem, value: any) => {
    const updated = [...lineItems];
    updated[index] = {
      ...updated[index],
      [field]: value,
    };

    // Auto-calculate amount
    if (field === 'quantity' || field === 'unit_price') {
      const quantity = field === 'quantity' ? value : updated[index].quantity || 0;
      const unitPrice = field === 'unit_price' ? value : updated[index].unit_price || 0;
      updated[index].amount = quantity * unitPrice;
    }

    onChange(updated);
  };

  const subtotal = lineItems.reduce((sum, item) => sum + (item.amount || 0), 0);

  return (
    <div className="space-y-4">
      <div className="overflow-x-auto">
        <table className="table table-sm">
          <thead>
            <tr>
              <th>Description</th>
              <th className="w-24">Quantity</th>
              <th className="w-32">Unit Price</th>
              <th className="w-32">Amount</th>
              <th className="w-12"></th>
            </tr>
          </thead>
          <tbody>
            {lineItems.length === 0 ? (
              <tr>
                <td colSpan={5} className="text-center text-base-content/60 py-8">
                  No line items yet. Click "Add Line Item" to get started.
                </td>
              </tr>
            ) : (
              lineItems.map((item, index) => (
                <tr key={index}>
                  <td>
                    <textarea
                      className="textarea textarea-bordered textarea-sm w-full"
                      placeholder="Description"
                      value={item.description || ''}
                      onChange={(e) => updateLineItem(index, 'description', e.target.value)}
                      rows={2}
                    />
                  </td>
                  <td>
                    <input
                      type="number"
                      className="input input-bordered input-sm w-full"
                      placeholder="1"
                      min="0"
                      step="0.01"
                      value={item.quantity || ''}
                      onChange={(e) =>
                        updateLineItem(index, 'quantity', parseFloat(e.target.value) || 0)
                      }
                    />
                  </td>
                  <td>
                    <div className="join w-full">
                      <span className="join-item btn btn-sm btn-disabled">$</span>
                      <input
                        type="number"
                        className="input input-bordered input-sm join-item w-full"
                        placeholder="0.00"
                        min="0"
                        step="0.01"
                        value={item.unit_price || ''}
                        onChange={(e) =>
                          updateLineItem(index, 'unit_price', parseFloat(e.target.value) || 0)
                        }
                      />
                    </div>
                  </td>
                  <td>
                    <div className="font-semibold">
                      ${(item.amount || 0).toFixed(2)}
                    </div>
                  </td>
                  <td>
                    <button
                      type="button"
                      onClick={() => removeLineItem(index)}
                      className="btn btn-sm btn-ghost btn-circle text-error"
                    >
                      <TrashIcon className="w-4 h-4" />
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
          {lineItems.length > 0 && (
            <tfoot>
              <tr>
                <td colSpan={3} className="text-right font-semibold">
                  Subtotal:
                </td>
                <td className="font-bold text-lg">
                  ${subtotal.toFixed(2)} {currency}
                </td>
                <td></td>
              </tr>
            </tfoot>
          )}
        </table>
      </div>

      <button
        type="button"
        onClick={addLineItem}
        className="btn btn-outline btn-sm"
      >
        <PlusIcon className="w-4 h-4" />
        Add Line Item
      </button>
    </div>
  );
}

