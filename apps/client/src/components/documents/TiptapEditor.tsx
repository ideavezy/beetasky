import { useState, useEffect, useImperativeHandle, forwardRef } from 'react';
import { useEditor, EditorContent, Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import Placeholder from '@tiptap/extension-placeholder';
import {
  BoldIcon,
  ItalicIcon,
  UnderlineIcon as UnderlineIconHero,
  ListBulletIcon,
  HashtagIcon,
  Bars3BottomLeftIcon,
  Bars3Icon,
  Bars3BottomRightIcon,
} from '@heroicons/react/24/outline';

interface TiptapEditorProps {
  content: string;
  onChange: (content: string) => void;
  placeholder?: string;
  editable?: boolean;
  minimal?: boolean;
}

export interface TiptapEditorHandle {
  insertText: (text: string) => void;
  getEditor: () => Editor | null;
}

export const TiptapEditor = forwardRef<TiptapEditorHandle, TiptapEditorProps>(
  function TiptapEditor(
    {
  content,
  onChange,
  placeholder = 'Start typing...',
  editable = true,
      minimal = true,
    },
    ref
  ) {
    const [isFocused, setIsFocused] = useState(false);
    const [showToolbar, setShowToolbar] = useState(false);

  const editor = useEditor({
    extensions: [
      StarterKit,
      Underline,
      TextAlign.configure({
        types: ['heading', 'paragraph'],
      }),
      Placeholder.configure({
        placeholder,
          emptyEditorClass: 'is-editor-empty',
      }),
    ],
    content,
    editable,
    onUpdate: ({ editor }) => {
      onChange(editor.getHTML());
    },
      onFocus: () => setIsFocused(true),
      onBlur: () => {
        // Delay hiding toolbar to allow button clicks
        setTimeout(() => setIsFocused(false), 200);
      },
      editorProps: {
        attributes: {
          class: minimal
            ? 'prose prose-sm max-w-none focus:outline-none min-h-[80px] text-base-content'
            : 'prose prose-sm max-w-none focus:outline-none min-h-[200px] p-4 bg-base-100 text-base-content',
        },
      },
    });

    // Expose methods via ref
    useImperativeHandle(ref, () => ({
      insertText: (text: string) => {
        if (editor) {
          editor.chain().focus().insertContent(text).run();
        }
      },
      getEditor: () => editor,
    }), [editor]);

    // Show toolbar when focused or when text is selected
    useEffect(() => {
      if (!editor) return;

      const handleSelectionUpdate = () => {
        const { from, to } = editor.state.selection;
        setShowToolbar(from !== to || isFocused);
      };

      editor.on('selectionUpdate', handleSelectionUpdate);
      return () => {
        editor.off('selectionUpdate', handleSelectionUpdate);
      };
    }, [editor, isFocused]);

  if (!editor) {
    return null;
  }

    // Floating toolbar component
    const FloatingToolbar = () => (
      <div
        className={`flex items-center gap-0.5 px-1 py-1 bg-base-300 rounded-lg shadow-lg border border-base-100 mb-2 transition-all duration-150 ${
          showToolbar || isFocused
            ? 'opacity-100 translate-y-0'
            : 'opacity-0 -translate-y-2 pointer-events-none'
        }`}
      >
        <button
          type="button"
          onMouseDown={(e) => e.preventDefault()}
          onClick={() => editor.chain().focus().toggleBold().run()}
          className={`p-1.5 rounded hover:bg-base-200 transition-colors ${
            editor.isActive('bold') ? 'bg-base-200 text-primary' : 'text-base-content'
          }`}
          title="Bold"
        >
          <BoldIcon className="w-4 h-4" />
        </button>
        <button
          type="button"
          onMouseDown={(e) => e.preventDefault()}
          onClick={() => editor.chain().focus().toggleItalic().run()}
          className={`p-1.5 rounded hover:bg-base-200 transition-colors ${
            editor.isActive('italic') ? 'bg-base-200 text-primary' : 'text-base-content'
          }`}
          title="Italic"
        >
          <ItalicIcon className="w-4 h-4" />
        </button>
        <button
          type="button"
          onMouseDown={(e) => e.preventDefault()}
          onClick={() => editor.chain().focus().toggleUnderline().run()}
          className={`p-1.5 rounded hover:bg-base-200 transition-colors ${
            editor.isActive('underline') ? 'bg-base-200 text-primary' : 'text-base-content'
          }`}
          title="Underline"
        >
          <UnderlineIconHero className="w-4 h-4" />
        </button>

        <div className="w-px h-4 bg-base-content/20 mx-1" />

        <button
          type="button"
          onMouseDown={(e) => e.preventDefault()}
          onClick={() => editor.chain().focus().toggleBulletList().run()}
          className={`p-1.5 rounded hover:bg-base-200 transition-colors ${
            editor.isActive('bulletList') ? 'bg-base-200 text-primary' : 'text-base-content'
          }`}
          title="Bullet List"
        >
          <ListBulletIcon className="w-4 h-4" />
        </button>
        <button
          type="button"
          onMouseDown={(e) => e.preventDefault()}
          onClick={() => editor.chain().focus().toggleOrderedList().run()}
          className={`p-1.5 rounded hover:bg-base-200 transition-colors ${
            editor.isActive('orderedList') ? 'bg-base-200 text-primary' : 'text-base-content'
          }`}
          title="Numbered List"
        >
          <HashtagIcon className="w-4 h-4" />
        </button>

        <div className="w-px h-4 bg-base-content/20 mx-1" />

        <button
          type="button"
          onMouseDown={(e) => e.preventDefault()}
          onClick={() => editor.chain().focus().setTextAlign('left').run()}
          className={`p-1.5 rounded hover:bg-base-200 transition-colors ${
            editor.isActive({ textAlign: 'left' }) ? 'bg-base-200 text-primary' : 'text-base-content'
          }`}
          title="Align Left"
        >
          <Bars3BottomLeftIcon className="w-4 h-4" />
        </button>
        <button
          type="button"
          onMouseDown={(e) => e.preventDefault()}
          onClick={() => editor.chain().focus().setTextAlign('center').run()}
          className={`p-1.5 rounded hover:bg-base-200 transition-colors ${
            editor.isActive({ textAlign: 'center' }) ? 'bg-base-200 text-primary' : 'text-base-content'
          }`}
          title="Align Center"
        >
          <Bars3Icon className="w-4 h-4" />
        </button>
        <button
          type="button"
          onMouseDown={(e) => e.preventDefault()}
          onClick={() => editor.chain().focus().setTextAlign('right').run()}
          className={`p-1.5 rounded hover:bg-base-200 transition-colors ${
            editor.isActive({ textAlign: 'right' }) ? 'bg-base-200 text-primary' : 'text-base-content'
          }`}
          title="Align Right"
        >
          <Bars3BottomRightIcon className="w-4 h-4" />
        </button>
      </div>
    );

    if (minimal) {
      return (
        <div className="relative">
          {editable && <FloatingToolbar />}

          <EditorContent editor={editor} />

          <style>{`
          .is-editor-empty:first-child::before {
            content: attr(data-placeholder);
            float: left;
            color: hsl(var(--bc) / 0.3);
            pointer-events: none;
            height: 0;
          }
          .ProseMirror:focus {
            outline: none;
          }
          .ProseMirror p {
            margin: 0.5em 0;
          }
          .ProseMirror p:first-child {
            margin-top: 0;
          }
          .ProseMirror ul, .ProseMirror ol {
            padding-left: 1.5em;
            margin: 0.5em 0;
          }
        `}</style>
        </div>
      );
    }

    // Full editor with static toolbar
  return (
    <div className="border border-base-300 rounded-lg overflow-hidden">
      {editable && (
        <div className="bg-base-200 border-b border-base-300 p-2 flex items-center gap-1 flex-wrap">
          <button
            type="button"
            onClick={() => editor.chain().focus().toggleBold().run()}
            className={`btn btn-sm btn-ghost ${editor.isActive('bold') ? 'bg-base-300' : ''}`}
            title="Bold (Ctrl+B)"
          >
            <BoldIcon className="w-4 h-4" />
          </button>
          <button
            type="button"
            onClick={() => editor.chain().focus().toggleItalic().run()}
            className={`btn btn-sm btn-ghost ${editor.isActive('italic') ? 'bg-base-300' : ''}`}
            title="Italic (Ctrl+I)"
          >
            <ItalicIcon className="w-4 h-4" />
          </button>
          <button
            type="button"
            onClick={() => editor.chain().focus().toggleUnderline().run()}
            className={`btn btn-sm btn-ghost ${editor.isActive('underline') ? 'bg-base-300' : ''}`}
            title="Underline (Ctrl+U)"
          >
            <UnderlineIconHero className="w-4 h-4" />
          </button>

          <div className="divider divider-horizontal mx-1"></div>

          <button
            type="button"
            onClick={() => editor.chain().focus().toggleBulletList().run()}
            className={`btn btn-sm btn-ghost ${editor.isActive('bulletList') ? 'bg-base-300' : ''}`}
            title="Bullet List"
          >
            <ListBulletIcon className="w-4 h-4" />
          </button>
          <button
            type="button"
            onClick={() => editor.chain().focus().toggleOrderedList().run()}
            className={`btn btn-sm btn-ghost ${editor.isActive('orderedList') ? 'bg-base-300' : ''}`}
            title="Numbered List"
          >
              <HashtagIcon className="w-4 h-4" />
          </button>

          <div className="divider divider-horizontal mx-1"></div>

          <button
            type="button"
            onClick={() => editor.chain().focus().setTextAlign('left').run()}
            className={`btn btn-sm btn-ghost ${editor.isActive({ textAlign: 'left' }) ? 'bg-base-300' : ''}`}
            title="Align Left"
          >
            <Bars3BottomLeftIcon className="w-4 h-4" />
          </button>
          <button
            type="button"
            onClick={() => editor.chain().focus().setTextAlign('center').run()}
            className={`btn btn-sm btn-ghost ${editor.isActive({ textAlign: 'center' }) ? 'bg-base-300' : ''}`}
            title="Align Center"
          >
            <Bars3Icon className="w-4 h-4" />
          </button>
          <button
            type="button"
            onClick={() => editor.chain().focus().setTextAlign('right').run()}
            className={`btn btn-sm btn-ghost ${editor.isActive({ textAlign: 'right' }) ? 'bg-base-300' : ''}`}
            title="Align Right"
          >
            <Bars3BottomRightIcon className="w-4 h-4" />
          </button>
        </div>
      )}

      <EditorContent
        editor={editor}
        className="prose prose-sm max-w-none p-4 min-h-[200px] bg-base-100"
      />
    </div>
  );
}
);
