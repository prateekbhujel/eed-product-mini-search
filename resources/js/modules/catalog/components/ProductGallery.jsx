import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

export default function ProductGallery({ product }) {
    const images = useMemo(() => (
        (product.gallery_urls?.length ? product.gallery_urls : [product.image_url]).filter(Boolean)
    ), [product.gallery_urls, product.image_url]);
    const [activeIndex, setActiveIndex] = useState(0);
    const activeImage = images[activeIndex] ?? product.image_url;

    useEffect(() => {
        setActiveIndex(0);
    }, [product.id]);

    function move(step) {
        setActiveIndex((current) => (current + step + images.length) % images.length);
    }

    return (
        <div className="detail-gallery">
            <div className="gallery-frame">
                {images.length > 1 && (
                    <button className="gallery-nav is-prev" type="button" onClick={() => move(-1)} aria-label="Previous product image">
                        <ChevronLeft size={20} aria-hidden="true" />
                    </button>
                )}
                <img src={activeImage} alt={`${product.brand} ${product.name}`} loading="eager" decoding="async" fetchPriority="high" />
                {images.length > 1 && (
                    <button className="gallery-nav is-next" type="button" onClick={() => move(1)} aria-label="Next product image">
                        <ChevronRight size={20} aria-hidden="true" />
                    </button>
                )}
            </div>

            {images.length > 1 && (
                <div className="gallery-thumbs" aria-label="Product images">
                    {images.map((image, index) => (
                        <button
                            className={index === activeIndex ? 'is-active' : ''}
                            type="button"
                            key={image}
                            onClick={() => setActiveIndex(index)}
                            aria-label={`Show image ${index + 1}`}
                        >
                            <img src={image} alt="" loading="lazy" decoding="async" />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
