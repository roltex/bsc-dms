import { useQuery } from '@tanstack/react-query'
import { fetchSubstitutions } from '../api/substitutions'
import { Card, CardBody, CardHeader } from '../components/ui/Card'
import { SkeletonCard } from '../components/ui/Skeleton'

export default function SubstitutionsPage() {
  const { data: substitutions, isLoading } = useQuery({
    queryKey: ['substitutions'],
    queryFn: fetchSubstitutions,
  })

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-slate-900 dark:text-white">Substitutions</h1>
        <p className="text-slate-500 dark:text-slate-400 mt-1">
          View your active substitution assignments. When you are substituting for a colleague, their tasks will appear in your queue.
        </p>
      </div>

      {isLoading ? (
        <div className="space-y-4">
          <SkeletonCard />
          <SkeletonCard />
        </div>
      ) : !substitutions?.length ? (
        <Card>
          <CardBody>
            <div className="py-8 text-center text-slate-500 dark:text-slate-400">
              <svg className="h-12 w-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <p>No active substitutions.</p>
              <p className="text-sm mt-1">Substitutions are managed by administrators in the admin panel.</p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <div className="space-y-4">
          {substitutions.map((sub) => (
            <Card key={sub.id}>
              <CardHeader>
                <h3 className="font-medium text-slate-900 dark:text-white">
                  Substituting for: {sub.user?.name ?? 'Unknown'}
                </h3>
              </CardHeader>
              <CardBody>
                <dl className="grid gap-3 sm:grid-cols-3">
                  <div>
                    <dt className="text-sm text-slate-500 dark:text-slate-400">User Email</dt>
                    <dd className="text-slate-900 dark:text-white">{sub.user?.email ?? '---'}</dd>
                  </div>
                  <div>
                    <dt className="text-sm text-slate-500 dark:text-slate-400">From</dt>
                    <dd className="text-slate-900 dark:text-white">{new Date(sub.from_date).toLocaleDateString()}</dd>
                  </div>
                  <div>
                    <dt className="text-sm text-slate-500 dark:text-slate-400">To</dt>
                    <dd className="text-slate-900 dark:text-white">{new Date(sub.to_date).toLocaleDateString()}</dd>
                  </div>
                </dl>
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
