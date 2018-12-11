import { TestBed } from '@angular/core/testing';

import { DatapruebaService } from './dataprueba.service';

describe('DatapruebaService', () => {
  beforeEach(() => TestBed.configureTestingModule({}));

  it('should be created', () => {
    const service: DatapruebaService = TestBed.get(DatapruebaService);
    expect(service).toBeTruthy();
  });
});
