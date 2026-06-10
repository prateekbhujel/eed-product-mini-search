import { Minus, Plus, ShoppingCart, Trash2, X } from 'lucide-react';

export default function CartDrawer({ open, items, onClose, onQtyChange, onRemove }) {
    if (!open) {
        return null;
    }

    const subtotal = items.reduce((total, item) => total + Number(item.product.price.value) * item.qty, 0);
    const itemCount = items.reduce((total, item) => total + item.qty, 0);

    return (
        <>
            <button className="cart-backdrop" type="button" aria-label="Close basket" onClick={onClose} />
            <aside className="cart-drawer" aria-label="Basket">
                <div className="cart-head">
                    <div>
                        <strong>Basket</strong>
                        <span>{itemCount} item{itemCount === 1 ? '' : 's'}</span>
                    </div>
                    <button className="icon-button" type="button" aria-label="Close basket" onClick={onClose}>
                        <X size={18} aria-hidden="true" />
                    </button>
                </div>

                {items.length === 0 ? (
                    <div className="cart-empty">
                        <ShoppingCart size={34} aria-hidden="true" />
                        <h2>Your basket is empty</h2>
                        <p>Add a part from the catalog to check quantities and total.</p>
                    </div>
                ) : (
                    <>
                        <div className="cart-items">
                            {items.map((item) => (
                                <article className="cart-line" key={item.product.id}>
                                    <img src={item.product.image_url} alt="" />
                                    <div>
                                        <a href={`/products/${item.product.slug}`}>{item.product.name}</a>
                                        <span>{item.product.brand} | {item.product.sku}</span>
                                        <strong>{item.product.price.display}</strong>
                                        <div className="qty-stepper" aria-label={`Quantity for ${item.product.name}`}>
                                            <button type="button" onClick={() => onQtyChange(item.product.id, item.qty - 1)} aria-label="Decrease quantity">
                                                <Minus size={14} aria-hidden="true" />
                                            </button>
                                            <span>{item.qty}</span>
                                            <button type="button" onClick={() => onQtyChange(item.product.id, item.qty + 1)} aria-label="Increase quantity">
                                                <Plus size={14} aria-hidden="true" />
                                            </button>
                                            <button type="button" className="remove-line" onClick={() => onRemove(item.product.id)}>
                                                <Trash2 size={14} aria-hidden="true" />
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            ))}
                        </div>

                        <div className="cart-total">
                            <span>Subtotal</span>
                            <strong>{subtotal.toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })}</strong>
                            <button type="button">Proceed to checkout</button>
                        </div>
                    </>
                )}
            </aside>
        </>
    );
}
