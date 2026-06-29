import { Link } from 'react-router-dom'
import Button from '../components/ui/Button'

export default function NotFoundPage() {
  return (
    <div className="flex flex-col items-center justify-center min-h-[400px] text-center">
      <h1 className="text-6xl font-bold text-slate-200 dark:text-slate-700 mb-4">404</h1>
      <h2 className="text-xl font-semibold text-slate-900 dark:text-white mb-2">Page Not Found</h2>
      <p className="text-slate-500 dark:text-slate-400 mb-6">The page you're looking for doesn't exist or has been moved.</p>
      <Link to="/dashboard">
        <Button>Back to Dashboard</Button>
      </Link>
    </div>
  )
}
