import { useState } from 'react'
import ConfirmedDonationSummary from '../../../components/ConfirmedDonationSummary'
import DonationReportInbox from '../../../components/DonationReportInbox'

export default function ConfirmationsView() {
  const [summaryVersion, setSummaryVersion] = useState(0)
  return <div className="container">
    <h1 className="sr-only">Donation confirmations</h1>
    <ConfirmedDonationSummary version={summaryVersion} />
    <DonationReportInbox admin onChanged={() => setSummaryVersion(value => value + 1)} />
  </div>
}
