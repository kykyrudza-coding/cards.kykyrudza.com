import { i18n } from '../i18n'

export const formatChips = (amount: number) =>
  new Intl.NumberFormat(i18n.global.locale.value).format(amount)
