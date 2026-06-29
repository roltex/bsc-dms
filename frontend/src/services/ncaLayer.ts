const NCA_LAYER_URL = 'wss://127.0.0.1:13579'

interface NcaLayerResponse {
  result?: string
  errorCode?: string
  errorMessage?: string
}

export class NcaLayerClient {
  private ws: WebSocket | null = null

  async connect(): Promise<boolean> {
    return new Promise((resolve) => {
      try {
        this.ws = new WebSocket(NCA_LAYER_URL)
        this.ws.onopen = () => resolve(true)
        this.ws.onerror = () => resolve(false)
        setTimeout(() => {
          if (this.ws?.readyState !== WebSocket.OPEN) {
            resolve(false)
          }
        }, 3000)
      } catch {
        resolve(false)
      }
    })
  }

  async signCms(data: string, keyType: string = 'PKCS12'): Promise<{ signature: string; error?: string }> {
    if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
      const connected = await this.connect()
      if (!connected) {
        return { signature: '', error: 'NCALayer is not running. Please start NCALayer and try again.' }
      }
    }

    return new Promise((resolve) => {
      const request = {
        module: 'kz.gov.pki.knca.commonUtils',
        method: 'signXml',
        args: [keyType, 'SIGNATURE', data, '', ''],
      }

      this.ws!.onmessage = (event) => {
        try {
          const response: NcaLayerResponse = JSON.parse(event.data)
          if (response.errorCode && response.errorCode !== '0') {
            resolve({ signature: '', error: response.errorMessage || 'NCALayer signing error' })
          } else {
            resolve({ signature: response.result || '', error: undefined })
          }
        } catch {
          resolve({ signature: '', error: 'Invalid response from NCALayer' })
        }
      }

      this.ws!.onerror = () => {
        resolve({ signature: '', error: 'NCALayer connection error' })
      }

      this.ws!.send(JSON.stringify(request))

      setTimeout(() => {
        resolve({ signature: '', error: 'NCALayer signing timeout. Please try again.' })
      }, 120000)
    })
  }

  disconnect(): void {
    if (this.ws) {
      this.ws.close()
      this.ws = null
    }
  }
}

export async function isNcaLayerAvailable(): Promise<boolean> {
  const client = new NcaLayerClient()
  const connected = await client.connect()
  client.disconnect()
  return connected
}
