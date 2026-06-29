import { forwardRef, useCallback, useEffect, useImperativeHandle, useRef } from 'react'
import SignaturePadLib from 'signature_pad'

export interface SignaturePadHandle {
  toDataURL: () => string
  isEmpty: () => boolean
  clear: () => void
}

interface SignaturePadProps {
  width?: number
  height?: number
  penColor?: string
  className?: string
  onEnd?: () => void
}

const SignaturePad = forwardRef<SignaturePadHandle, SignaturePadProps>(
  ({ width, height = 200, penColor, className = '', onEnd }, ref) => {
    const canvasRef = useRef<HTMLCanvasElement>(null)
    const padRef = useRef<SignaturePadLib | null>(null)
    const containerRef = useRef<HTMLDivElement>(null)

    const resizeCanvas = useCallback(() => {
      const canvas = canvasRef.current
      const container = containerRef.current
      if (!canvas || !container) return

      const ratio = Math.max(window.devicePixelRatio || 1, 1)
      const w = width ?? container.clientWidth
      canvas.width = w * ratio
      canvas.height = height * ratio
      canvas.style.width = `${w}px`
      canvas.style.height = `${height}px`

      const ctx = canvas.getContext('2d')
      if (ctx) ctx.scale(ratio, ratio)

      padRef.current?.clear()
    }, [width, height])

    useEffect(() => {
      const canvas = canvasRef.current
      if (!canvas) return

      const pad = new SignaturePadLib(canvas, {
        penColor: penColor ?? '#1a2236',
        backgroundColor: 'rgba(0,0,0,0)',
      })

      if (onEnd) pad.addEventListener('endStroke', onEnd)

      padRef.current = pad
      resizeCanvas()

      const observer = new ResizeObserver(() => resizeCanvas())
      if (containerRef.current) observer.observe(containerRef.current)

      return () => {
        if (onEnd) pad.removeEventListener('endStroke', onEnd)
        pad.off()
        observer.disconnect()
      }
    }, [penColor, onEnd, resizeCanvas])

    useImperativeHandle(ref, () => ({
      toDataURL: () => padRef.current?.toDataURL('image/png') ?? '',
      isEmpty: () => padRef.current?.isEmpty() ?? true,
      clear: () => padRef.current?.clear(),
    }))

    return (
      <div ref={containerRef} className={`relative ${className}`}>
        <canvas
          ref={canvasRef}
          className="w-full rounded-lg border-2 border-dashed border-slate-300 dark:border-slate-600 bg-white cursor-crosshair touch-none"
          style={{ height: `${height}px` }}
        />
        <p className="absolute bottom-3 left-0 right-0 text-center text-xs text-slate-400 dark:text-slate-500 pointer-events-none select-none">
          Draw your signature above
        </p>
      </div>
    )
  }
)

SignaturePad.displayName = 'SignaturePad'
export default SignaturePad
