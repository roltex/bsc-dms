import { useEditor, EditorContent } from '@tiptap/react'
import { Extension } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import TextAlign from '@tiptap/extension-text-align'
import { TextStyle } from '@tiptap/extension-text-style'
import Color from '@tiptap/extension-color'
import Highlight from '@tiptap/extension-highlight'
import { Table, TableRow, TableCell, TableHeader } from '@tiptap/extension-table'
import Image from '@tiptap/extension-image'
import { useCallback, useRef, useState } from 'react'

const FontSize = Extension.create({
  name: 'fontSize',
  addOptions() {
    return { types: ['textStyle'] }
  },
  addGlobalAttributes() {
    return [{
      types: this.options.types,
      attributes: {
        fontSize: {
          default: null,
          parseHTML: (element: HTMLElement) => element.style.fontSize || null,
          renderHTML: (attributes: Record<string, string>) => {
            if (!attributes.fontSize) return {}
            return { style: `font-size: ${attributes.fontSize}` }
          },
        },
      },
    }]
  },
})

const FontFamily = Extension.create({
  name: 'fontFamily',
  addOptions() {
    return { types: ['textStyle'] }
  },
  addGlobalAttributes() {
    return [{
      types: this.options.types,
      attributes: {
        fontFamily: {
          default: null,
          parseHTML: (element: HTMLElement) => element.style.fontFamily || null,
          renderHTML: (attributes: Record<string, string>) => {
            if (!attributes.fontFamily) return {}
            return { style: `font-family: ${attributes.fontFamily}` }
          },
        },
      },
    }]
  },
})

interface DocumentEditorProps {
  initialContent: string
  onSave: (html: string) => void
  saving: boolean
}

export default function DocumentEditor({ initialContent, onSave, saving }: DocumentEditorProps) {
  const imageInputRef = useRef<HTMLInputElement>(null)
  const [inTable, setInTable] = useState(false)

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        heading: { levels: [1, 2, 3, 4] },
      }),
      Underline,
      TextAlign.configure({ types: ['heading', 'paragraph'] }),
      TextStyle,
      FontSize,
      FontFamily,
      Color,
      Highlight.configure({ multicolor: true }),
      Table.configure({ resizable: true }),
      TableRow,
      TableCell,
      TableHeader,
      Image.configure({ inline: false, allowBase64: true }),
    ],
    content: initialContent,
    editorProps: {
      attributes: {
        class: 'doc-editor-content',
      },
    },
    onSelectionUpdate: ({ editor: e }) => {
      setInTable(e.isActive('table'))
    },
    onTransaction: ({ editor: e }) => {
      setInTable(e.isActive('table'))
    },
  })

  const handleSave = useCallback(() => {
    if (!editor) return
    onSave(editor.getHTML())
  }, [editor, onSave])

  const handleImageUpload = useCallback(() => {
    imageInputRef.current?.click()
  }, [])

  const handleImageFile = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file || !editor) return
    const reader = new FileReader()
    reader.onload = () => {
      editor.chain().focus().setImage({ src: reader.result as string }).run()
    }
    reader.readAsDataURL(file)
    e.target.value = ''
  }, [editor])

  if (!editor) return null

  return (
    <div className="doc-editor-wrapper">
      <style>{`
        .doc-editor-wrapper {
          display: flex;
          flex-direction: column;
          height: 100%;
          background: var(--editor-chrome, #f1f5f9);
        }
        .dark .doc-editor-wrapper {
          --editor-chrome: #0f172a;
        }
        .doc-editor-toolbar {
          display: flex;
          flex-wrap: wrap;
          gap: 2px;
          padding: 6px 10px;
          border-bottom: 1px solid var(--border-color, #e2e8f0);
          background: var(--toolbar-bg, #ffffff);
          align-items: center;
          flex-shrink: 0;
        }
        .dark .doc-editor-toolbar {
          --toolbar-bg: #1e293b;
          --border-color: rgba(148,163,184,0.2);
        }
        .doc-editor-toolbar .sep {
          width: 1px;
          height: 20px;
          background: var(--border-color, #e2e8f0);
          margin: 0 4px;
          flex-shrink: 0;
        }
        .doc-editor-toolbar button {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          min-width: 28px;
          height: 28px;
          padding: 0 4px;
          border: none;
          border-radius: 5px;
          background: transparent;
          color: var(--text-muted, #64748b);
          cursor: pointer;
          font-size: 11px;
          font-weight: 600;
          transition: all 0.15s;
          flex-shrink: 0;
          white-space: nowrap;
        }
        .dark .doc-editor-toolbar button {
          --text-muted: #94a3b8;
        }
        .doc-editor-toolbar button:hover {
          background: var(--hover-bg, #e2e8f0);
          color: var(--text, #1e293b);
        }
        .dark .doc-editor-toolbar button:hover {
          --hover-bg: rgba(148,163,184,0.15);
          --text: #f1f5f9;
        }
        .doc-editor-toolbar button.active {
          background: #3b82f6;
          color: white;
        }
        .doc-editor-toolbar button:disabled {
          opacity: 0.35;
          cursor: not-allowed;
        }
        .doc-editor-toolbar select {
          height: 28px;
          padding: 0 6px;
          border: 1px solid var(--border-color, #e2e8f0);
          border-radius: 5px;
          background: var(--toolbar-bg, #ffffff);
          color: var(--text-muted, #64748b);
          font-size: 12px;
          cursor: pointer;
          outline: none;
        }
        .dark .doc-editor-toolbar select {
          --toolbar-bg: #1e293b;
          --text-muted: #94a3b8;
          --border-color: rgba(148,163,184,0.25);
        }
        .doc-editor-toolbar input[type="color"] {
          width: 28px;
          height: 28px;
          padding: 2px;
          border: 1px solid var(--border-color, #e2e8f0);
          border-radius: 5px;
          cursor: pointer;
          background: transparent;
        }
        .doc-editor-table-bar {
          display: flex;
          flex-wrap: wrap;
          gap: 3px;
          padding: 5px 10px;
          border-bottom: 1px solid var(--border-color, #e2e8f0);
          background: var(--table-bar-bg, #f0fdf4);
          align-items: center;
          flex-shrink: 0;
        }
        .dark .doc-editor-table-bar {
          --table-bar-bg: rgba(16,185,129,0.08);
          --border-color: rgba(148,163,184,0.2);
        }
        .doc-editor-table-bar .tbl-label {
          font-size: 10px;
          font-weight: 700;
          text-transform: uppercase;
          letter-spacing: 0.05em;
          color: #059669;
          margin-right: 6px;
        }
        .dark .doc-editor-table-bar .tbl-label {
          color: #34d399;
        }
        .doc-editor-table-bar button {
          display: inline-flex;
          align-items: center;
          gap: 3px;
          height: 26px;
          padding: 0 8px;
          border: 1px solid #d1fae5;
          border-radius: 5px;
          background: white;
          color: #065f46;
          cursor: pointer;
          font-size: 11px;
          font-weight: 500;
          transition: all 0.15s;
          white-space: nowrap;
        }
        .dark .doc-editor-table-bar button {
          background: rgba(16,185,129,0.1);
          border-color: rgba(16,185,129,0.2);
          color: #6ee7b7;
        }
        .doc-editor-table-bar button:hover {
          background: #d1fae5;
          border-color: #a7f3d0;
        }
        .dark .doc-editor-table-bar button:hover {
          background: rgba(16,185,129,0.2);
        }
        .doc-editor-table-bar button.danger {
          color: #dc2626;
          border-color: #fecaca;
        }
        .dark .doc-editor-table-bar button.danger {
          color: #fca5a5;
          border-color: rgba(239,68,68,0.2);
          background: rgba(239,68,68,0.08);
        }
        .doc-editor-table-bar button.danger:hover {
          background: #fee2e2;
          border-color: #fca5a5;
        }
        .dark .doc-editor-table-bar button.danger:hover {
          background: rgba(239,68,68,0.15);
        }

        /* Page-like document area */
        .doc-editor-page-area {
          flex: 1;
          overflow-y: auto;
          background: var(--page-surround, #e2e8f0);
          padding: 24px 32px;
          display: flex;
          justify-content: center;
        }
        .dark .doc-editor-page-area {
          --page-surround: #0f172a;
        }
        .doc-editor-content {
          width: 100%;
          max-width: 816px;
          min-height: 1056px;
          background: var(--page-bg, #ffffff);
          box-shadow: 0 2px 12px rgba(0,0,0,0.12), 0 0 0 1px rgba(0,0,0,0.05);
          border-radius: 2px;
          padding: 72px 72px 72px 72px;
          font-family: 'Sylfaen', 'Times New Roman', 'DejaVu Serif', Georgia, serif;
          font-size: 12pt;
          line-height: 1.35;
          color: var(--text, #1e293b);
          outline: none;
        }
        .dark .doc-editor-content {
          --text: #e2e8f0;
          --page-bg: #1e293b;
          box-shadow: 0 2px 12px rgba(0,0,0,0.4), 0 0 0 1px rgba(148,163,184,0.15);
        }
        .doc-editor-content p { margin: 0 0 2pt 0; }
        .doc-editor-content h1 { font-size: 20pt; font-weight: bold; margin: 12pt 0 6pt; }
        .doc-editor-content h2 { font-size: 16pt; font-weight: bold; margin: 10pt 0 4pt; }
        .doc-editor-content h3 { font-size: 13pt; font-weight: bold; margin: 8pt 0 4pt; }
        .doc-editor-content h4 { font-size: 12pt; font-weight: bold; margin: 6pt 0 3pt; }
        .doc-editor-content table {
          border-collapse: collapse;
          width: 100%;
          margin: 4pt 0;
          table-layout: fixed;
        }
        .doc-editor-content td, .doc-editor-content th {
          border: 1px solid var(--table-border, #94a3b8);
          padding: 3pt 5pt;
          min-width: 40px;
          vertical-align: top;
          position: relative;
          word-wrap: break-word;
        }
        .dark .doc-editor-content td, .dark .doc-editor-content th {
          --table-border: rgba(148,163,184,0.35);
        }
        .doc-editor-content th {
          background: var(--th-bg, #f8fafc);
          font-weight: 600;
        }
        .dark .doc-editor-content th {
          --th-bg: rgba(148,163,184,0.1);
        }
        .doc-editor-content td p, .doc-editor-content th p {
          margin: 0;
        }
        .doc-editor-content .selectedCell::after {
          background: rgba(59,130,246,0.1);
          content: "";
          left: 0; right: 0; top: 0; bottom: 0;
          pointer-events: none;
          position: absolute;
          z-index: 2;
        }
        .doc-editor-content img {
          max-width: 100%;
          height: auto;
          display: block;
          margin: 8pt auto;
        }
        .doc-editor-content blockquote {
          border-left: 3px solid #3b82f6;
          padding-left: 12px;
          margin: 8pt 0 8pt 16pt;
          color: var(--text-muted, #64748b);
        }
        .doc-editor-content ul, .doc-editor-content ol {
          padding-left: 24pt;
          margin: 4pt 0;
        }
        .doc-editor-content hr {
          border: none;
          border-top: 1px solid var(--border-color, #e2e8f0);
          margin: 12pt 0;
        }
        .doc-editor-content mark {
          background-color: #fef08a;
          padding: 0 2px;
          border-radius: 2px;
        }
        .doc-editor-content .ProseMirror-selectednode {
          outline: 2px solid #3b82f6;
        }
        .doc-editor-content .tableWrapper {
          overflow-x: auto;
        }
        .doc-editor-content .column-resize-handle {
          background-color: #3b82f6;
          bottom: -2px;
          pointer-events: none;
          position: absolute;
          right: -2px;
          top: 0;
          width: 4px;
        }
        .doc-editor-footer {
          display: flex;
          align-items: center;
          justify-content: space-between;
          padding: 8px 14px;
          border-top: 1px solid var(--border-color, #e2e8f0);
          background: var(--toolbar-bg, #ffffff);
          flex-shrink: 0;
        }
        .dark .doc-editor-footer {
          --toolbar-bg: #1e293b;
          --border-color: rgba(148,163,184,0.2);
        }
      `}</style>

      {/* Main toolbar */}
      <div className="doc-editor-toolbar">
        <select
          value={
            editor.isActive('heading', { level: 1 }) ? 'h1' :
            editor.isActive('heading', { level: 2 }) ? 'h2' :
            editor.isActive('heading', { level: 3 }) ? 'h3' :
            editor.isActive('heading', { level: 4 }) ? 'h4' : 'p'
          }
          onChange={e => {
            const v = e.target.value
            if (v === 'p') editor.chain().focus().setParagraph().run()
            else editor.chain().focus().toggleHeading({ level: parseInt(v[1]) as 1|2|3|4 }).run()
          }}
        >
          <option value="p">Normal</option>
          <option value="h1">Heading 1</option>
          <option value="h2">Heading 2</option>
          <option value="h3">Heading 3</option>
          <option value="h4">Heading 4</option>
        </select>

        <div className="sep" />

        <button onClick={() => editor.chain().focus().toggleBold().run()} className={editor.isActive('bold') ? 'active' : ''} title="Bold"><b>B</b></button>
        <button onClick={() => editor.chain().focus().toggleItalic().run()} className={editor.isActive('italic') ? 'active' : ''} title="Italic"><i>I</i></button>
        <button onClick={() => editor.chain().focus().toggleUnderline().run()} className={editor.isActive('underline') ? 'active' : ''} title="Underline"><u>U</u></button>
        <button onClick={() => editor.chain().focus().toggleStrike().run()} className={editor.isActive('strike') ? 'active' : ''} title="Strikethrough"><s>S</s></button>

        <div className="sep" />

        <button onClick={() => editor.chain().focus().setTextAlign('left').run()} className={editor.isActive({ textAlign: 'left' }) ? 'active' : ''} title="Align Left">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 10H3M21 6H3M21 14H3M17 18H3"/></svg>
        </button>
        <button onClick={() => editor.chain().focus().setTextAlign('center').run()} className={editor.isActive({ textAlign: 'center' }) ? 'active' : ''} title="Center">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M18 10H6M21 6H3M21 14H3M18 18H6"/></svg>
        </button>
        <button onClick={() => editor.chain().focus().setTextAlign('right').run()} className={editor.isActive({ textAlign: 'right' }) ? 'active' : ''} title="Align Right">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 10H7M21 6H3M21 14H3M21 18H7"/></svg>
        </button>
        <button onClick={() => editor.chain().focus().setTextAlign('justify').run()} className={editor.isActive({ textAlign: 'justify' }) ? 'active' : ''} title="Justify">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 10H3M21 6H3M21 14H3M21 18H3"/></svg>
        </button>

        <div className="sep" />

        <button onClick={() => editor.chain().focus().toggleBulletList().run()} className={editor.isActive('bulletList') ? 'active' : ''} title="Bullet List">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
        </button>
        <button onClick={() => editor.chain().focus().toggleOrderedList().run()} className={editor.isActive('orderedList') ? 'active' : ''} title="Numbered List">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M10 6h11M10 12h11M10 18h11M4 6h1v4M4 10h2M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
        </button>

        <div className="sep" />

        <input
          type="color"
          title="Text Color"
          defaultValue="#000000"
          onChange={e => editor.chain().focus().setColor(e.target.value).run()}
        />
        <button
          onClick={() => editor.chain().focus().toggleHighlight({ color: '#fef08a' }).run()}
          className={editor.isActive('highlight') ? 'active' : ''}
          title="Highlight"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="2" y="18" width="20" height="4" rx="1" fill="#fef08a" stroke="currentColor" strokeWidth="1"/><path d="M9.5 2L4 14h3l1-3h8l1 3h3L14.5 2h-5zm-.5 7l2.5-5L14 9H9z"/></svg>
        </button>

        <div className="sep" />

        <button
          onClick={() => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()}
          title="Insert Table (3x3)"
          className={inTable ? 'active' : ''}
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg>
        </button>

        <div className="sep" />

        <button onClick={handleImageUpload} title="Insert Image">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
        </button>
        <input ref={imageInputRef} type="file" accept="image/*" className="hidden" onChange={handleImageFile} style={{ display: 'none' }} />

        <button onClick={() => editor.chain().focus().toggleBlockquote().run()} className={editor.isActive('blockquote') ? 'active' : ''} title="Quote">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M6 17h3l2-4V7H5v6h3l-2 4zm8 0h3l2-4V7h-6v6h3l-2 4z"/></svg>
        </button>
        <button onClick={() => editor.chain().focus().setHorizontalRule().run()} title="Horizontal Rule">—</button>

        <div className="sep" />

        <button onClick={() => editor.chain().focus().undo().run()} disabled={!editor.can().undo()} title="Undo">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 10h10a5 5 0 015 5v2M3 10l5-5M3 10l5 5"/></svg>
        </button>
        <button onClick={() => editor.chain().focus().redo().run()} disabled={!editor.can().redo()} title="Redo">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 10H11a5 5 0 00-5 5v2M21 10l-5-5M21 10l-5 5"/></svg>
        </button>
      </div>

      {/* Table operations bar */}
      {inTable && (
        <div className="doc-editor-table-bar">
          <span className="tbl-label">Table:</span>
          <button onClick={() => editor.chain().focus().addRowBefore().run()}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
            Row Above
          </button>
          <button onClick={() => editor.chain().focus().addRowAfter().run()}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
            Row Below
          </button>
          <button onClick={() => editor.chain().focus().addColumnBefore().run()}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
            Col Left
          </button>
          <button onClick={() => editor.chain().focus().addColumnAfter().run()}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
            Col Right
          </button>
          <button className="danger" onClick={() => editor.chain().focus().deleteRow().run()}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M5 12h14"/></svg>
            Del Row
          </button>
          <button className="danger" onClick={() => editor.chain().focus().deleteColumn().run()}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M5 12h14"/></svg>
            Del Col
          </button>
          <button onClick={() => editor.chain().focus().mergeCells().run()}>Merge</button>
          <button onClick={() => editor.chain().focus().splitCell().run()}>Split</button>
          <button className="danger" onClick={() => editor.chain().focus().deleteTable().run()}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
            Delete Table
          </button>
        </div>
      )}

      {/* Page-like content area */}
      <div className="doc-editor-page-area">
        <EditorContent editor={editor} />
      </div>

      <div className="doc-editor-footer">
        <span className="text-xs text-slate-400 dark:text-slate-500">
          Edit content here. Use the preview panel to see the final formatted document.
        </span>
        <button
          onClick={handleSave}
          disabled={saving}
          className="inline-flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          {saving ? (
            <>
              <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              Saving...
            </>
          ) : (
            <>
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
              Save & Update Preview
            </>
          )}
        </button>
      </div>
    </div>
  )
}
