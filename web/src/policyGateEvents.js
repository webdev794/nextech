// Opening the Seller Center's policy window (PolicyGate.jsx) from anywhere.

// Ask the seller to accept these policies; resolves true once all are signed,
// false if they close the window. Any page can call it.
export function requestPolicies(policies) {
  return new Promise((resolve) => {
    window.dispatchEvent(new CustomEvent('nextech:policies', { detail: { policies, resolve } }))
  })
}

// A 422 from the API listing policies to sign first: open the gate, and
// retry when they're signed. Returns true when the caller should retry.
export async function handlePolicyError(data) {
  if (!Array.isArray(data?.policies_required) || data.policies_required.length === 0) return false
  return requestPolicies(data.policies_required)
}
