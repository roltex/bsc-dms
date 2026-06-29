<x-filament-panels::page>
    <style>
        :root {
            --wfb-bg: #f8fafc;
            --wfb-bg-canvas: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            --wfb-border: rgba(100,116,139,0.25);
            --wfb-text: #1e293b;
            --wfb-text-muted: #64748b;
            --wfb-text-faint: #94a3b8;
            --wfb-surface: #ffffff;
            --wfb-surface-hover: #f1f5f9;
            --wfb-input-bg: #ffffff;
            --wfb-input-border: #cbd5e1;
            --wfb-toolbar-bg: rgba(241,245,249,0.8);
            --wfb-toolbar-border: rgba(100,116,139,0.15);
            --wfb-canvas-dot: rgba(100,116,139,0.25);
            --wfb-edge-color: rgba(100,116,139,0.45);
            --wfb-node-shadow: 0 4px 16px rgba(0,0,0,0.08);
            --wfb-card-bg: #ffffff;
            --wfb-modal-bg: #ffffff;
            --wfb-modal-overlay: rgba(0,0,0,0.3);
            --wfb-accent: #f59e0b;
            --wfb-accent-hover: #d97706;
        }
        .dark {
            --wfb-bg: #0f172a;
            --wfb-bg-canvas: linear-gradient(135deg, rgba(15,23,42,0.6) 0%, rgba(30,41,59,0.4) 100%);
            --wfb-border: rgba(148,163,184,0.15);
            --wfb-text: #f1f5f9;
            --wfb-text-muted: #94a3b8;
            --wfb-text-faint: rgba(148,163,184,0.5);
            --wfb-surface: rgba(30,41,59,0.6);
            --wfb-surface-hover: rgba(51,65,85,0.5);
            --wfb-input-bg: rgba(15,23,42,0.5);
            --wfb-input-border: rgba(148,163,184,0.25);
            --wfb-toolbar-bg: rgba(15,23,42,0.5);
            --wfb-toolbar-border: rgba(148,163,184,0.1);
            --wfb-canvas-dot: rgba(148,163,184,0.2);
            --wfb-edge-color: rgba(148,163,184,0.35);
            --wfb-node-shadow: 0 4px 20px rgba(0,0,0,0.35);
            --wfb-card-bg: rgba(30,41,59,0.85);
            --wfb-modal-bg: #1e293b;
            --wfb-modal-overlay: rgba(0,0,0,0.55);
        }

        .wfb-input {
            display: block; width: 100%; padding: 0.5rem 0.75rem;
            font-size: 0.875rem; line-height: 1.25rem; border-radius: 0.5rem;
            border: 1px solid var(--wfb-input-border);
            background: var(--wfb-input-bg); color: var(--wfb-text);
            outline: none; transition: border-color 0.15s;
            -webkit-appearance: none; appearance: none;
        }
        .wfb-input:focus { border-color: var(--wfb-accent); box-shadow: 0 0 0 3px rgba(245,158,11,0.15); }
        select.wfb-input { padding-right: 2rem; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3E%3C/svg%3E"); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em; }
        .wfb-label { display: block; font-size: 0.6875rem; font-weight: 600; margin-bottom: 0.25rem; color: var(--wfb-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

        .wfb-btn {
            display: inline-flex; align-items: center; gap: 0.375rem;
            padding: 0.4375rem 0.875rem; font-size: 0.8125rem; font-weight: 600;
            border-radius: 0.5rem; border: none; cursor: pointer;
            transition: all 0.15s; white-space: nowrap; color: var(--wfb-text);
        }
        .wfb-btn svg { width: 15px; height: 15px; flex-shrink: 0; }
        .wfb-btn-primary { background: var(--wfb-accent); color: #000; }
        .wfb-btn-primary:hover { background: var(--wfb-accent-hover); }
        .wfb-btn-primary:disabled { opacity: 0.35; cursor: not-allowed; }
        .wfb-btn-secondary { background: var(--wfb-surface); border: 1px solid var(--wfb-border); }
        .wfb-btn-secondary:hover { background: var(--wfb-surface-hover); }
        .wfb-btn-dark { background: rgba(100,116,139,0.2); border: 1px solid var(--wfb-border); }
        .wfb-btn-dark:hover { background: rgba(100,116,139,0.35); }
        .wfb-btn-icon { padding: 0.375rem; border-radius: 0.375rem; background: var(--wfb-surface); border: 1px solid var(--wfb-border); cursor: pointer; color: var(--wfb-text-muted); transition: all 0.15s; }
        .wfb-btn-icon:hover { background: var(--wfb-surface-hover); color: var(--wfb-text); }
        .wfb-btn-icon svg { width: 16px; height: 16px; }

        .wfb-palette-item {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 600;
            border-radius: 0.5rem; cursor: grab; user-select: none;
            border: 2px dashed; transition: all 0.15s;
        }
        .wfb-palette-item:active { cursor: grabbing; opacity: 0.7; }
        .wfb-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

        .wfb-palette-manager { border-color: rgba(96,165,250,0.5); background: rgba(96,165,250,0.08); color: #60a5fa; }
        .wfb-palette-manager:hover { background: rgba(96,165,250,0.18); }
        .wfb-palette-lawyer { border-color: rgba(167,139,250,0.5); background: rgba(167,139,250,0.08); color: #a78bfa; }
        .wfb-palette-lawyer:hover { background: rgba(167,139,250,0.18); }
        .wfb-palette-initiator { border-color: rgba(251,191,36,0.5); background: rgba(251,191,36,0.08); color: #fbbf24; }
        .wfb-palette-initiator:hover { background: rgba(251,191,36,0.18); }
        .wfb-palette-partner { border-color: rgba(244,114,182,0.5); background: rgba(244,114,182,0.08); color: #f472b6; }
        .wfb-palette-partner:hover { background: rgba(244,114,182,0.18); }
        .wfb-palette-gm { border-color: rgba(251,146,60,0.5); background: rgba(251,146,60,0.08); color: #fb923c; }
        .wfb-palette-gm:hover { background: rgba(251,146,60,0.18); }
        .wfb-palette-approve { border-color: rgba(52,211,153,0.5); background: rgba(52,211,153,0.08); color: #34d399; }
        .wfb-palette-approve:hover { background: rgba(52,211,153,0.18); }

        .wfb-canvas-outer {
            position: relative; overflow: hidden; border-radius: 0.75rem;
            border: 1px solid var(--wfb-border);
            background: var(--wfb-bg-canvas);
            height: 800px; cursor: grab;
        }
        .wfb-canvas-outer.is-panning { cursor: grabbing; }
        .wfb-canvas-outer.is-drawing-edge { cursor: crosshair; }

        .wfb-canvas-inner { position: absolute; top: 0; left: 0; width: 4000px; height: 4000px; transform-origin: 0 0; }

        .wfb-canvas-grid { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; }
        .wfb-canvas-svg { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 5; }
        .wfb-canvas-svg g { pointer-events: auto; }

        .wfb-node { position: absolute; display: flex; flex-direction: column; align-items: center; cursor: move; user-select: none; z-index: 10; }
        .wfb-node-body { position: relative; }

        .wfb-step-card {
            width: 148px; border-radius: 0.625rem;
            box-shadow: var(--wfb-node-shadow);
            border: 2px solid; overflow: hidden; text-align: center;
            padding: 0.5rem 0.375rem;
            background: var(--wfb-card-bg);
        }
        .wfb-step-manager { border-color: #3b82f6; }
        .wfb-step-lawyer { border-color: #8b5cf6; }
        .wfb-step-initiator { border-color: #f59e0b; }
        .wfb-step-partner { border-color: #ec4899; }
        .wfb-step-gm { border-color: #fb923c; }

        .wfb-step-role { font-size: 0.5625rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 1px; }
        .wfb-step-role-manager { color: #3b82f6; }
        .wfb-step-role-lawyer { color: #8b5cf6; }
        .wfb-step-role-initiator { color: #f59e0b; }
        .wfb-step-role-partner { color: #ec4899; }
        .wfb-step-role-gm { color: #fb923c; }
        .wfb-step-label { font-size: 0.75rem; font-weight: 600; color: var(--wfb-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .wfb-step-action { font-size: 0.5625rem; color: var(--wfb-text-faint); margin-top: 1px; text-transform: capitalize; }

        .wfb-handle {
            position: absolute; top: 50%; transform: translateY(-50%);
            width: 16px; height: 16px; border-radius: 50%;
            background: var(--wfb-card-bg); border: 2px solid var(--wfb-text-faint);
            cursor: crosshair; z-index: 20; opacity: 0; transition: all 0.15s;
            pointer-events: all;
        }
        .wfb-handle:hover { border-color: var(--wfb-accent); background: rgba(245,158,11,0.15); transform: translateY(-50%) scale(1.3); }
        .wfb-handle-out { right: -9px; }
        .wfb-handle-in { left: -9px; }

        .wfb-node-body:hover .wfb-handle { opacity: 1; }
        .is-drawing-edge .wfb-handle-in { opacity: 1 !important; }
        .is-drawing-edge .wfb-node-body:hover { outline: 2px solid var(--wfb-accent); outline-offset: 4px; border-radius: 0.75rem; }

        .wfb-node-drop-zone {
            position: absolute; inset: -12px; border-radius: 1rem; z-index: 15;
        }

        .wfb-delete-btn {
            position: absolute; top: -8px; right: -8px;
            width: 20px; height: 20px; border-radius: 50%;
            background: #ef4444; color: white; border: 2px solid var(--wfb-card-bg);
            font-size: 13px; line-height: 1; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: all 0.15s; z-index: 30;
        }
        .wfb-node-body:hover .wfb-delete-btn { opacity: 1; }
        .wfb-delete-btn:hover { background: #dc2626; transform: scale(1.15); }

        .wfb-node-label { margin-top: 4px; font-size: 0.6875rem; font-weight: 500; color: var(--wfb-text-muted); }

        .wfb-selected .wfb-step-card { box-shadow: 0 0 0 3px rgba(245,158,11,0.45), var(--wfb-node-shadow) !important; }

        .wfb-empty-state { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; }
        .wfb-empty-state svg { color: var(--wfb-text-faint); }
        .wfb-empty-state p { color: var(--wfb-text-faint); font-size: 0.875rem; margin-top: 0.75rem; }

        .wfb-modal-overlay { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: var(--wfb-modal-overlay); backdrop-filter: blur(4px); }
        .wfb-modal { background: var(--wfb-modal-bg); border-radius: 1rem; box-shadow: 0 20px 60px rgba(0,0,0,0.3); border: 1px solid var(--wfb-border); width: 100%; max-width: 26rem; padding: 1.5rem; }
        .wfb-modal h3 { font-size: 1.125rem; font-weight: 600; color: var(--wfb-text); margin-bottom: 1rem; }

        .wfb-toolbar { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; padding: 0.625rem 0.75rem; background: var(--wfb-toolbar-bg); border-radius: 0.75rem; border: 1px solid var(--wfb-toolbar-border); backdrop-filter: blur(8px); }
        .wfb-toolbar-sep { width: 1px; height: 22px; background: var(--wfb-border); flex-shrink: 0; }
        .wfb-toolbar-label { font-size: 0.6875rem; font-weight: 600; color: var(--wfb-text-faint); text-transform: uppercase; letter-spacing: 0.04em; }

        .wfb-zoom-controls { display: flex; align-items: center; gap: 0.25rem; background: var(--wfb-surface); border: 1px solid var(--wfb-border); border-radius: 0.5rem; padding: 0.125rem; }
        .wfb-zoom-controls button { padding: 0.25rem 0.375rem; border: none; background: transparent; cursor: pointer; border-radius: 0.375rem; color: var(--wfb-text-muted); transition: all 0.15s; display: flex; align-items: center; }
        .wfb-zoom-controls button:hover { background: var(--wfb-surface-hover); color: var(--wfb-text); }
        .wfb-zoom-controls button svg { width: 14px; height: 14px; }
        .wfb-zoom-controls span { font-size: 0.6875rem; font-weight: 600; min-width: 36px; text-align: center; color: var(--wfb-text-muted); }

        .wfb-meta-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.75rem; }
        @media (max-width: 900px) { .wfb-meta-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 500px) { .wfb-meta-grid { grid-template-columns: 1fr; } }

        .wfb-toggle-wrap { display: flex; flex-direction: column; justify-content: flex-start; }
        .wfb-toggle { position: relative; display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; }
        .wfb-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
        .wfb-toggle-track {
            width: 40px; height: 22px; border-radius: 11px;
            background: var(--wfb-input-border); transition: background 0.2s;
            position: relative; flex-shrink: 0;
        }
        .wfb-toggle input:checked + .wfb-toggle-track { background: #22c55e; }
        .wfb-toggle-thumb {
            position: absolute; top: 2px; left: 2px;
            width: 18px; height: 18px; border-radius: 50%;
            background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }
        .wfb-toggle input:checked + .wfb-toggle-track .wfb-toggle-thumb { transform: translateX(18px); }
        .wfb-toggle-text { font-size: 0.8125rem; font-weight: 500; color: var(--wfb-text-muted); }

        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    </style>

    <div
        x-data="flowBuilder()"
        style="display:flex;flex-direction:column;gap:0.75rem;"
    >
        {{-- Route selector --}}
        <div class="wfb-meta-grid" wire:ignore>
            <div>
                <label class="wfb-label">Select Route</label>
                <select class="wfb-input" x-ref="routeSelect" x-on:change="selectRoute(parseInt($event.target.value))">
                    <option value="">— Choose a route —</option>
                    @foreach ($routes as $r)
                        <option value="{{ $r['id'] }}" @selected($r['id'] == $record)>{{ $r['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="wfb-label">Name</label>
                <input type="text" class="wfb-input" x-model="routeName" @input.debounce.300ms="$wire.set('routeName', routeName)" placeholder="Route name" />
            </div>
            <div>
                <label class="wfb-label">Slug</label>
                <input type="text" class="wfb-input" x-model="routeSlug" @input.debounce.300ms="$wire.set('routeSlug', routeSlug)" placeholder="route-slug" />
            </div>
            <div>
                <label class="wfb-label">Description</label>
                <input type="text" class="wfb-input" x-model="routeDesc" @input.debounce.300ms="$wire.set('routeDescription', routeDesc)" placeholder="Short description" />
            </div>
            <div class="wfb-toggle-wrap">
                <label class="wfb-label">Active</label>
                <label class="wfb-toggle">
                    <input type="checkbox" x-model="routeActive" @change="$wire.set('routeIsActive', routeActive)" />
                    <span class="wfb-toggle-track"><span class="wfb-toggle-thumb"></span></span>
                    <span class="wfb-toggle-text" x-text="routeActive ? 'Visible in frontend' : 'Hidden'"></span>
                </label>
            </div>
        </div>

        {{-- Toolbar --}}
        <div class="wfb-toolbar">
            <button type="button" @click="createRoute()" class="wfb-btn wfb-btn-dark">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                New Route
            </button>
            @if ($aiAvailable)
            <button type="button" @click="aiModalOpen = true" class="wfb-btn" style="background:linear-gradient(135deg,#8b5cf6,#6366f1);color:#fff;">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" /></svg>
                AI Flow Builder
            </button>
            @endif
            <div class="wfb-toolbar-sep"></div>
            <span class="wfb-toolbar-label">Drag to canvas:</span>

            <div draggable="true" @dragstart="startDragNew($event, 'manager', 'review')" class="wfb-palette-item wfb-palette-manager">
                <span class="wfb-dot" style="background:#3b82f6"></span> Manager Review
            </div>
            <div draggable="true" @dragstart="startDragNew($event, 'lawyer', 'review')" class="wfb-palette-item wfb-palette-lawyer">
                <span class="wfb-dot" style="background:#8b5cf6"></span> Lawyer Review
            </div>
            <div draggable="true" @dragstart="startDragNew($event, 'initiator', 'sign')" class="wfb-palette-item wfb-palette-initiator">
                <span class="wfb-dot" style="background:#f59e0b"></span> Initiator Action
            </div>
            <div draggable="true" @dragstart="startDragNew($event, 'partner', 'review')" class="wfb-palette-item wfb-palette-partner">
                <span class="wfb-dot" style="background:#ec4899"></span> Partner Action
            </div>
            <div draggable="true" @dragstart="startDragNew($event, 'gm', 'approve')" class="wfb-palette-item wfb-palette-gm">
                <span class="wfb-dot" style="background:#fb923c"></span> GM Approval
            </div>
            <div draggable="true" @dragstart="startDragNew($event, 'manager', 'approve')" class="wfb-palette-item wfb-palette-approve">
                <span class="wfb-dot" style="background:#10b981"></span> Final Approval
            </div>

            <div style="margin-left:auto;display:flex;align-items:center;gap:0.5rem;">
                {{-- Zoom controls --}}
                <div class="wfb-zoom-controls">
                    <button type="button" @click="zoomOut()" title="Zoom Out">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM13.5 10.5h-6" /></svg>
                    </button>
                    <span x-text="Math.round(zoom * 100) + '%'"></span>
                    <button type="button" @click="zoomIn()" title="Zoom In">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6" /></svg>
                    </button>
                    <button type="button" @click="centerView()" title="Center View" style="border-left:1px solid var(--wfb-border);margin-left:2px;padding-left:6px;">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 003.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0120.25 6v1.5m0 9V18A2.25 2.25 0 0118 20.25h-1.5m-9 0H6A2.25 2.25 0 013.75 18v-1.5" /></svg>
                    </button>
                </div>

                <button type="button" @click="autoLayout()" class="wfb-btn wfb-btn-secondary">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" /></svg>
                    Auto Layout
                </button>
                <button type="button" @click="save()" class="wfb-btn wfb-btn-primary" :disabled="!hasRoute">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    Save Flow
                </button>
            </div>
        </div>

        {{-- Canvas --}}
        <div id="wfb-canvas-outer" wire:ignore
            class="wfb-canvas-outer"
            :class="{ 'is-panning': isPanning, 'is-drawing-edge': drawingEdge }"
            @wheel.prevent="handleWheel($event)"
            @mousedown="handleOuterMouseDown($event)"
            @mousemove="handleOuterMouseMove($event)"
            @mouseup="handleOuterMouseUp($event)"
            @mouseleave="handleOuterMouseUp($event)"
            @dragover.prevent
            @drop.prevent="handleDrop($event)"
        >
            <div id="wfb-canvas-inner" class="wfb-canvas-inner" :style="canvasTransform">
                {{-- Grid --}}
                <svg class="wfb-canvas-grid" style="width:4000px;height:4000px;">
                    <defs>
                        <pattern id="wfb-dots" width="24" height="24" patternUnits="userSpaceOnUse">
                            <circle cx="12" cy="12" r="1" fill="var(--wfb-canvas-dot)"/>
                        </pattern>
                    </defs>
                    <rect width="4000" height="4000" fill="url(#wfb-dots)"/>
                </svg>

                {{-- Edges SVG - rendered imperatively to avoid Alpine x-for inside SVG --}}
                <svg class="wfb-canvas-svg" x-ref="edgesSvg" style="width:4000px;height:4000px;"
                    x-effect="renderEdges()">
                    <defs>
                        <marker id="wfb-arrow" viewBox="0 0 10 8" refX="9" refY="4" markerWidth="7" markerHeight="5" orient="auto-start-reverse">
                            <path d="M 0 0 L 10 4 L 0 8 z" fill="var(--wfb-edge-color)" />
                        </marker>
                        <marker id="wfb-arrow-draw" viewBox="0 0 10 8" refX="9" refY="4" markerWidth="7" markerHeight="5" orient="auto-start-reverse">
                            <path d="M 0 0 L 10 4 L 0 8 z" fill="#f59e0b" />
                        </marker>
                        <marker id="wfb-arrow-green" viewBox="0 0 10 8" refX="9" refY="4" markerWidth="7" markerHeight="5" orient="auto-start-reverse"><path d="M 0 0 L 10 4 L 0 8 z" fill="#22c55e" /></marker>
                        <marker id="wfb-arrow-red" viewBox="0 0 10 8" refX="9" refY="4" markerWidth="7" markerHeight="5" orient="auto-start-reverse"><path d="M 0 0 L 10 4 L 0 8 z" fill="#ef4444" /></marker>
                        <marker id="wfb-arrow-orange" viewBox="0 0 10 8" refX="9" refY="4" markerWidth="7" markerHeight="5" orient="auto-start-reverse"><path d="M 0 0 L 10 4 L 0 8 z" fill="#f97316" /></marker>
                        <marker id="wfb-arrow-blue" viewBox="0 0 10 8" refX="9" refY="4" markerWidth="7" markerHeight="5" orient="auto-start-reverse"><path d="M 0 0 L 10 4 L 0 8 z" fill="#3b82f6" /></marker>
                        <marker id="wfb-arrow-purple" viewBox="0 0 10 8" refX="9" refY="4" markerWidth="7" markerHeight="5" orient="auto-start-reverse"><path d="M 0 0 L 10 4 L 0 8 z" fill="#a855f7" /></marker>
                    </defs>
                    <g x-ref="edgesGroup"></g>
                </svg>

                {{-- Nodes --}}
                <template x-for="node in nodes" :key="node.id">
                    <div class="wfb-node"
                        :style="'left:'+node.x+'px;top:'+node.y+'px;z-index:'+(draggingNode?.id===node.id?50:10)"
                        @mousedown.stop="startDragNode($event, node)"
                        @dblclick.stop="editNode(node)"
                    >
                        {{-- Drop zone for edge connection (larger hit area) --}}
                        <div class="wfb-node-drop-zone"
                            x-show="drawingEdge && edgeStart?.id !== node.id"
                            @mouseup.stop="endDrawEdge(node)"></div>

                        <div class="wfb-node-body" :class="{ 'wfb-selected': selectedNode?.id === node.id }">
                            <div class="wfb-step-card" :class="'wfb-step-' + (node.role || 'manager')">
                                <div style="display:flex;align-items:center;justify-content:center;gap:4px;margin-bottom:2px">
                                    <span class="wfb-dot" :style="'background:' + ({manager:'#3b82f6',lawyer:'#8b5cf6',initiator:'#f59e0b',partner:'#ec4899',gm:'#fb923c'}[node.role] || '#3b82f6')"></span>
                                    <span class="wfb-step-role" :class="'wfb-step-role-' + (node.role || 'manager')" x-text="node.role || 'manager'"></span>
                                </div>
                                <div class="wfb-step-label" x-text="node.label"></div>
                                <div class="wfb-step-action">
                                    <span x-text="node.actionType || 'review'"></span>
                                    <template x-if="typeof node.durationDays === 'number'">
                                        <span style="display:inline-block;margin-left:4px;padding:1px 5px;border-radius:6px;background:rgba(59,130,246,0.15);color:#3b82f6;font-size:9px;font-weight:600" x-text="node.durationDays + 'd'"></span>
                                    </template>
                                    <template x-if="node.config && node.config.can_edit_attachments">
                                        <svg style="display:inline;width:10px;height:10px;vertical-align:middle;margin-left:2px;opacity:0.6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
                                    </template>
                                </div>
                            </div>

                            <div class="wfb-handle wfb-handle-out" @mousedown.stop.prevent="startDrawEdge($event, node)"></div>
                            <div class="wfb-handle wfb-handle-in" @mouseup.stop="endDrawEdge(node)"></div>
                            <button class="wfb-delete-btn" @click.stop="deleteNode(node)">&times;</button>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Empty state --}}
            <template x-if="!hasRoute">
                <div class="wfb-empty-state">
                    <div style="text-align:center">
                        <svg xmlns="http://www.w3.org/2000/svg" style="width:56px;height:56px;margin:0 auto" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25H6A2.25 2.25 0 003.75 6v2.25A2.25 2.25 0 006 10.5zm0 9.75h2.25A2.25 2.25 0 0010.5 18v-2.25a2.25 2.25 0 00-2.25-2.25H6a2.25 2.25 0 00-2.25 2.25V18A2.25 2.25 0 006 20.25zm9.75-9.75H18a2.25 2.25 0 002.25-2.25V6A2.25 2.25 0 0018 3.75h-2.25A2.25 2.25 0 0013.5 6v2.25a2.25 2.25 0 002.25 2.25z" /></svg>
                        <p>Select a route or create a new one to start building</p>
                    </div>
                </div>
            </template>
        </div>

        {{-- Edit node modal --}}
        <template x-if="editingNode">
            <div class="wfb-modal-overlay" @click.self="editingNode = null" @keydown.escape.window="editingNode = null">
                <div class="wfb-modal">
                    <h3>Edit Step</h3>
                    <div style="display:flex;flex-direction:column;gap:0.75rem">
                        <div>
                            <label class="wfb-label">Label</label>
                            <input type="text" class="wfb-input" x-model="editingNode.label" @keydown.enter="applyNodeEdit()" />
                        </div>
                        <div>
                            <label class="wfb-label">Role</label>
                            <select class="wfb-input" x-model="editingNode.role">
                                <option value="manager">Manager</option>
                                <option value="lawyer">Lawyer</option>
                                <option value="initiator">Initiator</option>
                                <option value="partner">Partner</option>
                                <option value="gm">General Manager</option>
                            </select>
                        </div>
                        <div>
                            <label class="wfb-label">Action Type</label>
                            <select class="wfb-input" x-model="editingNode.actionType">
                                <option value="review">Review</option>
                                <option value="approve">Approve</option>
                                <option value="sign">Sign</option>
                                <option value="submit">Submit</option>
                                <option value="upload_document">Upload Document</option>
                                <option value="confirm">Confirm</option>
                                <option value="create_final">Create Final Version</option>
                            </select>
                        </div>
                        <div>
                            <label class="wfb-label">Duration (days)</label>
                            <input type="number" class="wfb-input" x-model.number="editingNode.durationDays" min="0" max="365" step="1" />
                            <p style="font-size:0.6875rem;color:var(--wfb-text-faint);margin-top:0.25rem">Default number of days allotted for this step. Used to auto-calculate task deadlines.</p>
                        </div>
                        <div style="padding-top:0.25rem;border-top:1px solid var(--wfb-border)">
                            <label class="wfb-toggle">
                                <input type="checkbox" x-model="editingNode.canEditAttachments" />
                                <span class="wfb-toggle-track"><span class="wfb-toggle-thumb"></span></span>
                                <span class="wfb-toggle-text">Can edit attachments</span>
                            </label>
                            <p style="font-size:0.6875rem;color:var(--wfb-text-faint);margin-top:0.25rem;margin-left:3rem">Allow uploading new or replacement attachments at this step</p>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:0.5rem;padding-top:0.5rem">
                            <button @click="editingNode = null" class="wfb-btn wfb-btn-secondary">Cancel</button>
                            <button @click="applyNodeEdit()" class="wfb-btn wfb-btn-primary">Apply</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        {{-- Edit edge condition modal --}}
        <template x-if="editingEdge">
            <div class="wfb-modal-overlay" @click.self="editingEdge = null" @keydown.escape.window="editingEdge = null">
                <div class="wfb-modal">
                    <h3>Edge Condition</h3>
                    <div style="display:flex;flex-direction:column;gap:0.75rem">
                        <div>
                            <label class="wfb-label">Condition Type</label>
                            <select class="wfb-input" x-model="editingEdge.conditionType" @change="onConditionTypeChange()">
                                <option value="always">Always (unconditional)</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="needs_revision">Needs Revision</option>
                                <option value="amount_gte">Amount &gt;= (threshold)</option>
                                <option value="amount_lt">Amount &lt; (threshold)</option>
                                <option value="has_document">Has Document</option>
                                <option value="is_signed">Document Is Signed</option>
                                <option value="requires_gm">Requires GM (Amount >= Threshold)</option>
                            </select>
                        </div>
                        <template x-if="editingEdge.conditionType === 'amount_gte' || editingEdge.conditionType === 'amount_lt'">
                            <div>
                                <label class="wfb-label">Amount Value</label>
                                <input type="number" class="wfb-input" x-model.number="editingEdge.conditionValue" placeholder="e.g. 1000" min="0" step="0.01" />
                            </div>
                        </template>
                        <div style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0;border-top:1px solid var(--wfb-border)">
                            <div style="font-size:0.75rem;color:var(--wfb-text-muted)">
                                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;vertical-align:middle;margin-right:4px" :style="'background:'+conditionColor(editingEdge.conditionType)"></span>
                                <span x-text="conditionLabel(editingEdge.conditionType, editingEdge.conditionValue)"></span>
                            </div>
                        </div>
                        <div style="display:flex;justify-content:space-between;gap:0.5rem;padding-top:0.5rem">
                            <button @click="deleteEditingEdge()" class="wfb-btn" style="background:#ef4444;color:white">Delete Edge</button>
                            <div style="display:flex;gap:0.5rem">
                                <button @click="editingEdge = null" class="wfb-btn wfb-btn-secondary">Cancel</button>
                                <button @click="applyEdgeEdit()" class="wfb-btn wfb-btn-primary">Apply</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        {{-- AI Flow Builder modal --}}
        <template x-if="aiModalOpen">
            <div class="wfb-modal-overlay" @click.self="if(!aiLoading) aiModalOpen = false" @keydown.escape.window="if(!aiLoading) aiModalOpen = false">
                <div class="wfb-modal" style="max-width:32rem">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem">
                        <div style="width:36px;height:36px;border-radius:0.5rem;background:linear-gradient(135deg,#8b5cf6,#6366f1);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <svg style="width:20px;height:20px;color:#fff" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z" /></svg>
                        </div>
                        <div>
                            <h3 style="margin:0;font-size:1.125rem;font-weight:600;color:var(--wfb-text)">AI Flow Builder</h3>
                            <p style="margin:0;font-size:0.75rem;color:var(--wfb-text-muted)">Describe your workflow and AI will build it</p>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:0.75rem">
                        <div>
                            <label class="wfb-label">Describe your workflow</label>
                            <textarea
                                class="wfb-input"
                                x-model="aiDescription"
                                rows="5"
                                placeholder="Example: I need a service contract flow. First the manager reviews, then legal team reviews, GM approves, lawyer creates the final version, initiator confirms, partner reviews and signs, company signs, and finally a lawyer does final verification."
                                style="resize:vertical;min-height:100px"
                                :disabled="aiLoading"
                            ></textarea>
                        </div>
                        <div style="padding:0.625rem 0.75rem;border-radius:0.5rem;background:rgba(139,92,246,0.08);border:1px solid rgba(139,92,246,0.2)">
                            <p style="margin:0;font-size:0.75rem;color:var(--wfb-text-muted);line-height:1.5">
                                <strong style="color:var(--wfb-text)">Tips:</strong> Mention the roles (manager, lawyer, GM, partner, initiator), what each step does (review, approve, sign), and any revision/rejection paths you need. The AI will create the complete flow with transitions.
                            </p>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:0.5rem;padding-top:0.25rem">
                            <button @click="aiModalOpen = false" class="wfb-btn wfb-btn-secondary" :disabled="aiLoading">Cancel</button>
                            <button @click="generateAiFlow()" class="wfb-btn" style="background:linear-gradient(135deg,#8b5cf6,#6366f1);color:#fff;" :disabled="aiLoading || !aiDescription.trim()">
                                <template x-if="aiLoading">
                                    <svg style="width:16px;height:16px;animation:spin 1s linear infinite" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M2.985 19.644l3.181-3.182" /></svg>
                                </template>
                                <template x-if="!aiLoading">
                                    <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                                </template>
                                <span x-text="aiLoading ? 'Generating...' : 'Generate Flow'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('flowBuilder', () => ({
            nodes: [],
            edges: [],
            hasRoute: false,

            routeName: '',
            routeSlug: '',
            routeDesc: '',
            routeActive: false,

            selectedNode: null,
            editingNode: null,
            draggingNode: null,
            dragOffset: { x: 0, y: 0 },

            drawingEdge: false,
            edgeStart: null,
            edgeMousePos: { x: 0, y: 0 },

            newNodeData: null,
            _renderTick: 0,
            editingEdge: null,

            aiModalOpen: false,
            aiDescription: '',
            aiLoading: false,

            COND_COLORS: {
                approved: '#22c55e', rejected: '#ef4444', needs_revision: '#f97316',
                amount_gte: '#3b82f6', amount_lt: '#3b82f6',
                has_document: '#a855f7', is_signed: '#a855f7',
                requires_gm: '#fb923c', always: ''
            },
            COND_ARROWS: {
                approved: 'url(#wfb-arrow-green)', rejected: 'url(#wfb-arrow-red)', needs_revision: 'url(#wfb-arrow-orange)',
                amount_gte: 'url(#wfb-arrow-blue)', amount_lt: 'url(#wfb-arrow-blue)',
                has_document: 'url(#wfb-arrow-purple)', is_signed: 'url(#wfb-arrow-purple)',
                requires_gm: 'url(#wfb-arrow-orange)', always: 'url(#wfb-arrow)'
            },

            conditionColor(type) { return this.COND_COLORS[type] || 'var(--wfb-edge-color)'; },
            conditionLabel(type, val) {
                const labels = {
                    always: 'Always', approved: 'Approved', rejected: 'Rejected', needs_revision: 'Needs Revision',
                    amount_gte: 'Amount >= ' + (val ?? 0), amount_lt: 'Amount < ' + (val ?? 0),
                    has_document: 'Has Document', is_signed: 'Is Signed', requires_gm: 'Requires GM',
                };
                return labels[type] || type || 'Always';
            },

            init() {
                if (this.$wire.record) {
                    this.syncFromWire();
                }
            },

            selectRoute(id) {
                if (!id) return;
                const self = this;
                this.$wire.loadRoute(id).then(() => {
                    self.doSync();
                });
            },

            createRoute() {
                const self = this;
                this.$wire.createNewRoute().then(() => {
                    self.doSync();
                    self.rebuildRouteOptions();
                });
            },

            doSync() {
                this.nodes = [];
                this.edges = [];
                this.hasRoute = false;
                const self = this;
                this.$nextTick(() => {
                    self.syncFromWire();
                });
            },

            rebuildRouteOptions() {
                const routes = this.$wire.routes;
                const sel = this.$refs.routeSelect;
                if (!sel || !routes) return;
                const current = this.$wire.record || '';
                sel.innerHTML = '<option value="">— Choose a route —</option>';
                routes.forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r.id;
                    opt.textContent = r.name;
                    if (r.id == current) opt.selected = true;
                    sel.appendChild(opt);
                });
            },

            // Zoom & Pan
            zoom: 1,
            panX: 0,
            panY: 0,
            isPanning: false,
            panStart: { x: 0, y: 0 },
            panStartOffset: { x: 0, y: 0 },

            get canvasTransform() {
                return `transform: scale(${this.zoom}) translate(${this.panX}px, ${this.panY}px)`;
            },

            zoomIn() { this.zoom = Math.min(2, +(this.zoom + 0.15).toFixed(2)); },
            zoomOut() { this.zoom = Math.max(0.25, +(this.zoom - 0.15).toFixed(2)); },
            resetView() { this.zoom = 1; this.panX = 0; this.panY = 0; },

            centerView() {
                const self = this;
                setTimeout(() => { self._doCenterView(); }, 80);
            },

            _doCenterView() {
                if (!this.nodes.length) { this.resetView(); return; }
                const outer = document.getElementById('wfb-canvas-outer');
                if (!outer || outer.clientWidth < 10) { this.resetView(); return; }
                const vw = outer.clientWidth, vh = outer.clientHeight;
                const nw = this.NODE_W, nh = this.NODE_H, pad = 140;
                let x1 = Infinity, y1 = Infinity, x2 = -Infinity, y2 = -Infinity;
                this.nodes.forEach(n => {
                    x1 = Math.min(x1, n.x);
                    y1 = Math.min(y1, n.y);
                    x2 = Math.max(x2, n.x + nw);
                    y2 = Math.max(y2, n.y + nh);
                });
                x1 -= pad; y1 -= pad; x2 += pad; y2 += pad;
                const cw = x2 - x1, ch = y2 - y1;
                const z = +Math.max(0.4, Math.min(vw / cw, vh / ch, 1)).toFixed(2);
                this.zoom = z;
                const midX = (x1 + x2) / 2, midY = (y1 + y2) / 2;
                this.panX = Math.round((vw / (2 * z)) - midX);
                this.panY = Math.round((vh / (2 * z)) - midY);
            },

            handleWheel(event) {
                const delta = event.deltaY > 0 ? -0.08 : 0.08;
                this.zoom = Math.min(2, Math.max(0.25, +(this.zoom + delta).toFixed(2)));
            },

            screenToCanvas(clientX, clientY) {
                const outer = document.getElementById('wfb-canvas-outer').getBoundingClientRect();
                return {
                    x: (clientX - outer.left) / this.zoom - this.panX,
                    y: (clientY - outer.top) / this.zoom - this.panY,
                };
            },

            syncFromWire() {
                const data = this.$wire.canvasData;
                if (data && data.nodes) {
                    const allNodes = JSON.parse(JSON.stringify(data.nodes));
                    const removedIds = new Set();
                    this.nodes = allNodes.filter(n => {
                        if (n.type === 'start' || n.type === 'end') { removedIds.add(n.id); return false; }
                        return true;
                    });
                    const allEdges = JSON.parse(JSON.stringify(data.edges || []));
                    this.edges = allEdges.filter(e => !removedIds.has(e.from) && !removedIds.has(e.to));
                    this._recenterNodes();
                } else {
                    this.nodes = [];
                    this.edges = [];
                }
                this.hasRoute = !!this.$wire.record;
                this.selectedNode = null;
                this.editingNode = null;
                this.routeName = this.$wire.routeName || '';
                this.routeSlug = this.$wire.routeSlug || '';
                this.routeDesc = this.$wire.routeDescription || '';
                this.routeActive = this.$wire.routeIsActive;
                this._renderTick++;
                if (this.$refs.routeSelect) {
                    this.$refs.routeSelect.value = this.$wire.record || '';
                }
                this.centerView();
            },

            NODE_W: 148,
            NODE_H: 58,

            _recenterNodes() {
                if (!this.nodes.length) return;
                const nw = this.NODE_W, nh = this.NODE_H;
                let x1 = Infinity, y1 = Infinity, x2 = -Infinity, y2 = -Infinity;
                this.nodes.forEach(n => {
                    x1 = Math.min(x1, n.x); y1 = Math.min(y1, n.y);
                    x2 = Math.max(x2, n.x + nw); y2 = Math.max(y2, n.y + nh);
                });
                const dx = 2000 - (x1 + x2) / 2;
                const dy = 2000 - (y1 + y2) / 2;
                if (Math.abs(dx) > 100 || Math.abs(dy) > 100) {
                    this.nodes.forEach(n => {
                        n.x = Math.round(n.x + dx);
                        n.y = Math.round(n.y + dy);
                    });
                }
            },

            getNodeRight(node) {
                return { x: node.x + this.NODE_W, y: node.y + this.NODE_H / 2 };
            },
            getNodeLeft(node) {
                return { x: node.x, y: node.y + this.NODE_H / 2 };
            },

            calcEdgePath(fromNode, toNode, fromOff, toOff) {
                if (!fromNode || !toNode) return '';
                const s = this.getNodeRight(fromNode), e = this.getNodeLeft(toNode);
                const R = 8;
                const dx = e.x - s.x, dy = e.y - s.y, ady = Math.abs(dy);
                fromOff = fromOff || 0; toOff = toOff || 0;

                if (ady < 3 && dx > 10) return `M ${s.x} ${s.y} L ${e.x} ${e.y}`;

                if (dx > 50) {
                    const combined = fromOff + toOff;
                    const gap = 30 + combined * 22;
                    const cx = s.x + Math.max(20, Math.min(gap, dx * 0.45));
                    const dir = dy > 0 ? 1 : -1;
                    const r = Math.min(R, ady / 2, (cx - s.x) * 0.8, (e.x - cx) * 0.8);
                    if (r < 1) return `M ${s.x} ${s.y} L ${cx} ${s.y} L ${cx} ${e.y} L ${e.x} ${e.y}`;
                    return `M ${s.x} ${s.y} L ${cx-r} ${s.y} Q ${cx} ${s.y} ${cx} ${s.y+r*dir} L ${cx} ${e.y-r*dir} Q ${cx} ${e.y} ${cx+r} ${e.y} L ${e.x} ${e.y}`;
                }

                const nodeYs = this.nodes.map(n => n.y);
                const minY = Math.min(...nodeYs, s.y, e.y);
                const maxY = Math.max(...nodeYs, s.y, e.y) + this.NODE_H;
                const routeAbove = (fromOff % 2 === 0);
                const lane = Math.floor(fromOff / 2);
                const detY = routeAbove
                    ? minY - 60 - lane * 40
                    : maxY + 60 + lane * 40;
                const rx = s.x + 28 + fromOff * 22;
                const lx = e.x - 28 - toOff * 22;
                const r = Math.min(R, Math.abs(s.y - detY)/2, Math.abs(e.y - detY)/2, Math.abs(rx - s.x)*0.8, Math.abs(e.x - lx)*0.8);

                if (r < 1) return `M ${s.x} ${s.y} L ${rx} ${s.y} L ${rx} ${detY} L ${lx} ${detY} L ${lx} ${e.y} L ${e.x} ${e.y}`;

                if (routeAbove) {
                    return `M ${s.x} ${s.y} L ${rx-r} ${s.y} Q ${rx} ${s.y} ${rx} ${s.y-r} ` +
                           `L ${rx} ${detY+r} Q ${rx} ${detY} ${rx-r} ${detY} ` +
                           `L ${lx+r} ${detY} Q ${lx} ${detY} ${lx} ${detY+r} ` +
                           `L ${lx} ${e.y-r} Q ${lx} ${e.y} ${lx+r} ${e.y} ` +
                           `L ${e.x} ${e.y}`;
                } else {
                    return `M ${s.x} ${s.y} L ${rx-r} ${s.y} Q ${rx} ${s.y} ${rx} ${s.y+r} ` +
                           `L ${rx} ${detY-r} Q ${rx} ${detY} ${rx-r} ${detY} ` +
                           `L ${lx+r} ${detY} Q ${lx} ${detY} ${lx} ${detY-r} ` +
                           `L ${lx} ${e.y+r} Q ${lx} ${e.y} ${lx+r} ${e.y} ` +
                           `L ${e.x} ${e.y}`;
                }
            },

            renderEdges() {
                const g = this.$refs.edgesGroup;
                if (!g) return;
                const _tick = this._renderTick;
                const _e = this.edges, _n = this.nodes, _de = this.drawingEdge, _mp = this.edgeMousePos, _es = this.edgeStart, _ee = this.editingEdge;
                this.nodes.forEach(n => { let _x = n.x, _y = n.y; });
                this.edges.forEach(e => { let _c = e.condition; });

                while (g.firstChild) g.removeChild(g.firstChild);
                const ns = 'http://www.w3.org/2000/svg';
                const self = this;

                const fromCnt = {}, fromIdx = {}, toCnt = {}, toIdx = {};
                for (let i = 0; i < this.edges.length; i++) {
                    const fr = this.edges[i].from, to = this.edges[i].to;
                    if (!fromCnt[fr]) fromCnt[fr] = 0;
                    if (!toCnt[to]) toCnt[to] = 0;
                    fromIdx[i] = fromCnt[fr]++;
                    toIdx[i] = toCnt[to]++;
                }

                for (let i = 0; i < this.edges.length; i++) {
                    const edge = this.edges[i];
                    const f = this.nodes.find(n => n.id === edge.from);
                    const t = this.nodes.find(n => n.id === edge.to);
                    const d = this.calcEdgePath(f, t, fromIdx[i], toIdx[i]);
                    if (!d) continue;

                    const cType = edge.condition?.type || 'always';
                    const edgeColor = this.COND_COLORS[cType] || 'var(--wfb-edge-color)';
                    const arrowId = this.COND_ARROWS[cType] || 'url(#wfb-arrow)';
                    const strokeColor = edgeColor || 'var(--wfb-edge-color)';

                    const path = document.createElementNS(ns, 'path');
                    path.setAttribute('d', d);
                    path.setAttribute('fill', 'none');
                    path.setAttribute('stroke', strokeColor);
                    path.setAttribute('stroke-width', '2.5');
                    path.setAttribute('stroke-linecap', 'round');
                    path.setAttribute('stroke-linejoin', 'round');
                    path.setAttribute('marker-end', arrowId);
                    g.appendChild(path);

                    if (cType !== 'always' && f && t) {
                        const mid = this.getEdgeMidpoint(f, t, fromIdx[i], toIdx[i]);
                        const label = this.conditionLabel(cType, edge.condition?.value);
                        const fo = document.createElementNS(ns, 'foreignObject');
                        fo.setAttribute('x', mid.x - 60);
                        fo.setAttribute('y', mid.y - 12);
                        fo.setAttribute('width', '120');
                        fo.setAttribute('height', '24');
                        fo.style.pointerEvents = 'none';
                        fo.style.overflow = 'visible';
                        const pill = document.createElement('div');
                        pill.style.cssText = 'display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:9999px;font-size:10px;font-weight:600;white-space:nowrap;background:'+strokeColor+';color:#fff;box-shadow:0 1px 4px rgba(0,0,0,0.2);pointer-events:none;position:absolute;left:50%;transform:translateX(-50%)';
                        pill.textContent = label;
                        fo.appendChild(pill);
                        g.appendChild(fo);
                    }

                    const hitArea = document.createElementNS(ns, 'path');
                    hitArea.setAttribute('d', d);
                    hitArea.setAttribute('fill', 'none');
                    hitArea.setAttribute('stroke', 'transparent');
                    hitArea.setAttribute('stroke-width', '16');
                    hitArea.setAttribute('stroke-linecap', 'round');
                    hitArea.style.cursor = 'pointer';
                    hitArea.style.pointerEvents = 'stroke';
                    hitArea.dataset.edgeIdx = i;

                    const origColor = strokeColor;
                    hitArea.addEventListener('mouseenter', function() {
                        path.setAttribute('stroke', '#f59e0b');
                        path.setAttribute('stroke-width', '3.5');
                    });
                    hitArea.addEventListener('mouseleave', function() {
                        path.setAttribute('stroke', origColor);
                        path.setAttribute('stroke-width', '2.5');
                    });
                    hitArea.addEventListener('click', function(ev) {
                        ev.stopPropagation();
                        self.openEdgeEdit(parseInt(this.dataset.edgeIdx));
                    });
                    g.appendChild(hitArea);
                }

                if (this.drawingEdge && this.edgeStart) {
                    const s = this.getNodeRight(this.edgeStart);
                    const e = this.edgeMousePos;
                    const dx = e.x - s.x, dy = e.y - s.y, ady = Math.abs(dy);
                    let d;
                    if (ady < 5 && dx > 10) {
                        d = `M ${s.x} ${s.y} L ${e.x} ${e.y}`;
                    } else {
                        const midX = s.x + Math.min(40, Math.max(20, Math.abs(dx) * 0.4));
                        const dir = dy > 0 ? 1 : -1;
                        const r = Math.min(8, ady / 2, Math.abs(midX - s.x) * 0.8);
                        if (r < 1 || dx < 10) {
                            d = `M ${s.x} ${s.y} L ${midX} ${s.y} L ${midX} ${e.y} L ${e.x} ${e.y}`;
                        } else {
                            d = `M ${s.x} ${s.y} L ${midX-r} ${s.y} Q ${midX} ${s.y} ${midX} ${s.y+r*dir} L ${midX} ${e.y-r*dir} Q ${midX} ${e.y} ${midX+r} ${e.y} L ${e.x} ${e.y}`;
                        }
                    }
                    const path = document.createElementNS(ns, 'path');
                    path.setAttribute('d', d);
                    path.setAttribute('fill', 'none');
                    path.setAttribute('stroke', '#f59e0b');
                    path.setAttribute('stroke-width', '2');
                    path.setAttribute('stroke-dasharray', '6 4');
                    path.setAttribute('stroke-linejoin', 'round');
                    path.setAttribute('marker-end', 'url(#wfb-arrow-draw)');
                    g.appendChild(path);
                }
            },

            getEdgeMidpoint(fromNode, toNode, fromOff, toOff) {
                const s = this.getNodeRight(fromNode), e = this.getNodeLeft(toNode);
                const dx = e.x - s.x;
                fromOff = fromOff || 0; toOff = toOff || 0;
                if (dx > 50) {
                    const combined = fromOff + toOff;
                    const gap = 30 + combined * 22;
                    const cx = s.x + Math.max(20, Math.min(gap, dx * 0.45));
                    return { x: cx + 10, y: (s.y + e.y) / 2 };
                }
                const nodeYs = this.nodes.map(n => n.y);
                const minY = Math.min(...nodeYs, s.y, e.y);
                const maxY = Math.max(...nodeYs, s.y, e.y) + this.NODE_H;
                const routeAbove = (fromOff % 2 === 0);
                const lane = Math.floor(fromOff / 2);
                const detY = routeAbove ? minY - 60 - lane * 40 : maxY + 60 + lane * 40;
                const rx = s.x + 28 + fromOff * 22;
                const lx = e.x - 28 - toOff * 22;
                return { x: (rx + lx) / 2, y: detY };
            },

            // --- Palette drag ---
            startDragNew(event, role, actionType) {
                this.newNodeData = { role, actionType };
                event.dataTransfer.effectAllowed = 'copy';
                event.dataTransfer.setData('text/plain', 'new-node');
            },
            handleDrop(event) {
                if (!this.newNodeData || !this.hasRoute) return;
                const pos = this.screenToCanvas(event.clientX, event.clientY);
                const labels = { 'manager-review':'Manager Review', 'lawyer-review':'Lawyer Review', 'initiator-sign':'Initiator Action', 'partner-review':'Partner Review', 'partner-sign':'Partner Sign', 'partner-upload_document':'Partner Upload', 'partner-confirm':'Partner Confirm', 'manager-approve':'Final Approval', 'gm-approve':'GM Approval', 'lawyer-create_final':'Create Final Version' };
                this.nodes.push({
                    id: 'step-' + Date.now(), type: 'step',
                    x: Math.max(0, pos.x - 74), y: Math.max(0, pos.y - 29),
                    label: labels[this.newNodeData.role + '-' + this.newNodeData.actionType] || 'New Step',
                    role: this.newNodeData.role, actionType: this.newNodeData.actionType,
                    durationDays: 1,
                });
                this.newNodeData = null;
            },

            // --- Node dragging ---
            startDragNode(event, node) {
                if (this.drawingEdge) return;
                this.selectedNode = node;
                this.draggingNode = node;
                const pos = this.screenToCanvas(event.clientX, event.clientY);
                this.dragOffset = { x: pos.x - node.x, y: pos.y - node.y };
            },

            // --- Canvas panning & events ---
            handleOuterMouseDown(event) {
                if (this.drawingEdge || this.draggingNode) return;
                if (event.button === 0 || event.button === 1) {
                    this.isPanning = true;
                    this.panStart = { x: event.clientX, y: event.clientY };
                    this.panStartOffset = { x: this.panX, y: this.panY };
                }
            },

            handleOuterMouseMove(event) {
                if (this.isPanning) {
                    const dx = (event.clientX - this.panStart.x) / this.zoom;
                    const dy = (event.clientY - this.panStart.y) / this.zoom;
                    this.panX = this.panStartOffset.x + dx;
                    this.panY = this.panStartOffset.y + dy;
                    return;
                }
                if (this.draggingNode) {
                    const pos = this.screenToCanvas(event.clientX, event.clientY);
                    const idx = this.nodes.findIndex(n => n.id === this.draggingNode.id);
                    if (idx !== -1) {
                        this.nodes[idx].x = Math.max(0, pos.x - this.dragOffset.x);
                        this.nodes[idx].y = Math.max(0, pos.y - this.dragOffset.y);
                        this._renderTick++;
                    }
                }
                if (this.drawingEdge) {
                    this.edgeMousePos = this.screenToCanvas(event.clientX, event.clientY);
                }
            },

            handleOuterMouseUp(event) {
                this.draggingNode = null;
                this.isPanning = false;
                if (this.drawingEdge) {
                    this.drawingEdge = false;
                    this.edgeStart = null;
                }
            },

            // --- Edge drawing ---
            startDrawEdge(event, node) {
                event.stopPropagation();
                event.preventDefault();
                this.drawingEdge = true;
                this.edgeStart = node;
                this.edgeMousePos = this.screenToCanvas(event.clientX, event.clientY);
            },

            endDrawEdge(node) {
                if (!this.drawingEdge || !this.edgeStart) return;
                if (this.edgeStart.id === node.id) { this.drawingEdge = false; this.edgeStart = null; return; }
                if (!this.edges.some(e => e.from === this.edgeStart.id && e.to === node.id)) {
                    this.edges.push({ from: this.edgeStart.id, to: node.id });
                }
                this.drawingEdge = false;
                this.edgeStart = null;
            },

            // --- Node editing ---
            editNode(node) {
                const copy = JSON.parse(JSON.stringify(node));
                copy.canEditAttachments = !!(copy.config && copy.config.can_edit_attachments);
                copy.durationDays = (typeof copy.durationDays === 'number' && copy.durationDays >= 0) ? copy.durationDays : 1;
                this.editingNode = copy;
            },
            applyNodeEdit() {
                if (!this.editingNode) return;
                const idx = this.nodes.findIndex(n => n.id === this.editingNode.id);
                if (idx !== -1) {
                    const config = { ...(this.nodes[idx].config || {}), can_edit_attachments: !!this.editingNode.canEditAttachments };
                    const durationDays = Math.max(0, parseInt(this.editingNode.durationDays, 10) || 0);
                    this.nodes[idx] = { ...this.nodes[idx], label: this.editingNode.label, role: this.editingNode.role, actionType: this.editingNode.actionType, durationDays, config };
                    this.nodes = [...this.nodes];
                    this._renderTick++;
                }
                this.editingNode = null;
            },
            deleteNode(node) {
                this.nodes = this.nodes.filter(n => n.id !== node.id);
                this.edges = this.edges.filter(e => e.from !== node.id && e.to !== node.id);
                if (this.selectedNode?.id === node.id) this.selectedNode = null;
                this._renderTick++;
            },

            autoLayout() {
                const steps = this.nodes.filter(n => n.type === 'step').sort((a, b) => a.x - b.x);
                const spacing = 200;
                const sx = 2000 - (steps.length * spacing) / 2;
                const cy = 2000;
                steps.forEach((n, i) => { n.x = sx + i * spacing; n.y = cy; });
                this.edges = [];
                for (let i = 0; i < steps.length - 1; i++) this.edges.push({ from: steps[i].id, to: steps[i + 1].id });
                this.centerView();
            },

            // --- Edge editing ---
            openEdgeEdit(idx) {
                const edge = this.edges[idx];
                if (!edge) return;
                this.editingEdge = {
                    idx: idx,
                    from: edge.from,
                    to: edge.to,
                    conditionType: edge.condition?.type || 'always',
                    conditionValue: edge.condition?.value ?? null,
                };
            },
            onConditionTypeChange() {
                if (this.editingEdge && !['amount_gte','amount_lt'].includes(this.editingEdge.conditionType)) {
                    this.editingEdge.conditionValue = null;
                }
            },
            applyEdgeEdit() {
                if (!this.editingEdge) return;
                const idx = this.editingEdge.idx;
                if (idx >= 0 && idx < this.edges.length) {
                    const cType = this.editingEdge.conditionType;
                    let cond = null;
                    if (cType && cType !== 'always') {
                        cond = { type: cType };
                        if (['amount_gte','amount_lt'].includes(cType)) {
                            cond.value = parseFloat(this.editingEdge.conditionValue) || 0;
                        }
                    }
                    this.edges[idx] = { ...this.edges[idx], condition: cond };
                    this.edges = [...this.edges];
                    this._renderTick++;
                }
                this.editingEdge = null;
            },
            deleteEditingEdge() {
                if (!this.editingEdge) return;
                const idx = this.editingEdge.idx;
                this.edges.splice(idx, 1);
                this.edges = [...this.edges];
                this._renderTick++;
                this.editingEdge = null;
            },

            save() {
                if (!this.hasRoute) return;
                this.$wire.saveCanvas({ nodes: JSON.parse(JSON.stringify(this.nodes)), edges: JSON.parse(JSON.stringify(this.edges)) });
            },

            generateAiFlow() {
                if (this.aiLoading || !this.aiDescription.trim()) return;
                this.aiLoading = true;
                const self = this;
                this.$wire.generateWithAi(this.aiDescription.trim()).then(() => {
                    self.aiLoading = false;
                    self.aiModalOpen = false;
                    self.aiDescription = '';
                    self.doSync();
                    self.rebuildRouteOptions();
                }).catch(() => {
                    self.aiLoading = false;
                });
            },
        }));
    });
    </script>
</x-filament-panels::page>
