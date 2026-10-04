import DemandMapWidget from '../../../components/DemandMapWidget'

export default function DemandMapView() {
  return (
    <div className="container" style={{ padding: 'var(--space-6) 0' }}>
      <DemandMapWidget compact={false} showFilters={true} />
    </div>
  )
}
