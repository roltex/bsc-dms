import { useEditor, EditorContent } from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import { Table, TableRow, TableCell, TableHeader } from '@tiptap/extension-table'
import { useState, useCallback, useImperativeHandle, forwardRef } from 'react'

interface TableEditorProps {
  columns: string[]
  labels?: Record<string, string>
  initialRows?: Record<string, string>[]
}

export interface TableEditorHandle {
  getRows: () => Record<string, string>[]
}

function buildInitialHtml(columns: string[], labels: Record<string, string>, rows: Record<string, string>[]): string {
  const getLabel = (col: string) => labels[col] || col.replace(/_/g, ' ')
  let html = '<table><thead><tr>'
  html += '<th>#</th>'
  for (const col of columns) {
    html += `<th>${getLabel(col)}</th>`
  }
  html += '</tr></thead><tbody>'
  for (let i = 0; i < rows.length; i++) {
    html += '<tr>'
    html += `<td>${i + 1}</td>`
    for (const col of columns) {
      html += `<td>${rows[i][col] || ''}</td>`
    }
    html += '</tr>'
  }
  html += '</tbody></table>'
  return html
}

const TableEditor = forwardRef<TableEditorHandle, TableEditorProps>(
  function TableEditor({ columns, labels = {}, initialRows }, ref) {
    const [, setInTable] = useState(true)

    const defaultRows = initialRows && initialRows.length > 0
      ? initialRows
      : [Object.fromEntries(columns.map(c => [c, '']))]

    const editor = useEditor({
      extensions: [
        StarterKit.configure({
          heading: false,
          blockquote: false,
          codeBlock: false,
          horizontalRule: false,
          bulletList: false,
          orderedList: false,
          listItem: false,
        }),
        Table.configure({ resizable: true }),
        TableRow,
        TableCell,
        TableHeader,
      ],
      content: buildInitialHtml(columns, labels, defaultRows),
      editorProps: {
        attributes: {
          class: 'table-editor-content',
        },
      },
      onSelectionUpdate: ({ editor: e }) => {
        setInTable(e.isActive('table'))
      },
      onTransaction: ({ editor: e }) => {
        setInTable(e.isActive('table'))
      },
    })

    const getRows = useCallback((): Record<string, string>[] => {
      if (!editor) return []
      const html = editor.getHTML()
      const parser = new DOMParser()
      const doc = parser.parseFromString(html, 'text/html')
      const table = doc.querySelector('table')
      if (!table) return []

      const tbody = table.querySelector('tbody') || table
      const trs = tbody.querySelectorAll('tr')
      const result: Record<string, string>[] = []

      trs.forEach(tr => {
        const tds = tr.querySelectorAll('td')
        if (tds.length === 0) return
        const row: Record<string, string> = {}
        const startIdx = tds.length > columns.length ? 1 : 0
        columns.forEach((col, i) => {
          const td = tds[startIdx + i]
          row[col] = td ? (td.textContent?.trim() || '') : ''
        })
        const hasContent = columns.some(c => row[c] !== '')
        if (hasContent) result.push(row)
      })

      return result.length > 0 ? result : [Object.fromEntries(columns.map(c => [c, '']))]
    }, [editor, columns])

    useImperativeHandle(ref, () => ({ getRows }), [getRows])

    if (!editor) return null

    return (
      <div className="table-editor-wrapper">
        <style>{`
          .table-editor-wrapper {
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 10px;
            overflow: hidden;
            background: var(--bg, #ffffff);
          }
          .dark .table-editor-wrapper {
            --border-color: rgba(148,163,184,0.2);
            --bg: #1e293b;
          }
          .table-editor-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            padding: 6px 10px;
            border-bottom: 1px solid var(--border-color, #e2e8f0);
            background: var(--bar-bg, #f8fafc);
            align-items: center;
          }
          .dark .table-editor-bar {
            --bar-bg: #0f172a;
            --border-color: rgba(148,163,184,0.2);
          }
          .table-editor-bar button {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            height: 26px;
            padding: 0 8px;
            border: 1px solid var(--btn-border, #e2e8f0);
            border-radius: 5px;
            background: var(--btn-bg, white);
            color: var(--btn-color, #475569);
            cursor: pointer;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.15s;
            white-space: nowrap;
          }
          .dark .table-editor-bar button {
            --btn-bg: rgba(148,163,184,0.08);
            --btn-border: rgba(148,163,184,0.15);
            --btn-color: #94a3b8;
          }
          .table-editor-bar button:hover {
            background: var(--btn-hover, #f1f5f9);
          }
          .dark .table-editor-bar button:hover {
            --btn-hover: rgba(148,163,184,0.15);
          }
          .table-editor-bar button.danger {
            color: #dc2626;
            border-color: #fecaca;
          }
          .dark .table-editor-bar button.danger {
            color: #fca5a5;
            border-color: rgba(239,68,68,0.2);
          }
          .table-editor-bar button.danger:hover {
            background: #fee2e2;
          }
          .dark .table-editor-bar button.danger:hover {
            background: rgba(239,68,68,0.1);
          }
          .table-editor-bar .bar-sep {
            width: 1px;
            height: 18px;
            background: var(--border-color, #e2e8f0);
            margin: 0 4px;
          }
          .table-editor-content {
            padding: 12px;
            outline: none;
            min-height: 80px;
          }
          .table-editor-content table {
            border-collapse: collapse;
            width: 100%;
          }
          .table-editor-content td, .table-editor-content th {
            border: 1px solid var(--table-border, #cbd5e1);
            padding: 6px 10px;
            min-width: 60px;
            vertical-align: top;
            font-size: 13px;
            position: relative;
          }
          .dark .table-editor-content td, .dark .table-editor-content th {
            --table-border: rgba(148,163,184,0.3);
            color: #e2e8f0;
          }
          .table-editor-content th {
            background: var(--th-bg, #f1f5f9);
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--th-color, #64748b);
          }
          .dark .table-editor-content th {
            --th-bg: rgba(148,163,184,0.08);
            --th-color: #94a3b8;
          }
          .table-editor-content .selectedCell::after {
            background: rgba(59,130,246,0.1);
            content: "";
            left: 0; right: 0; top: 0; bottom: 0;
            pointer-events: none;
            position: absolute;
            z-index: 2;
          }
          .table-editor-content .tableWrapper {
            overflow-x: auto;
          }
          .table-editor-content .column-resize-handle {
            background-color: #3b82f6;
            bottom: -2px;
            pointer-events: none;
            position: absolute;
            right: -2px;
            top: 0;
            width: 4px;
          }
          .table-editor-content p {
            margin: 0;
          }
        `}</style>

        <div className="table-editor-bar">
          <button type="button" onClick={() => editor.chain().focus().addRowBefore().run()}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
            Row Above
          </button>
          <button type="button" onClick={() => editor.chain().focus().addRowAfter().run()}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
            Row Below
          </button>
          <button type="button" onClick={() => editor.chain().focus().addColumnBefore().run()}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
            Col Left
          </button>
          <button type="button" onClick={() => editor.chain().focus().addColumnAfter().run()}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
            Col Right
          </button>
          <div className="bar-sep" />
          <button type="button" className="danger" onClick={() => editor.chain().focus().deleteRow().run()}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M5 12h14"/></svg>
            Del Row
          </button>
          <button type="button" className="danger" onClick={() => editor.chain().focus().deleteColumn().run()}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M5 12h14"/></svg>
            Del Col
          </button>
          <div className="bar-sep" />
          <button type="button" onClick={() => editor.chain().focus().mergeCells().run()}>Merge</button>
          <button type="button" onClick={() => editor.chain().focus().splitCell().run()}>Split</button>
        </div>

        <EditorContent editor={editor} />
      </div>
    )
  }
)

export default TableEditor
